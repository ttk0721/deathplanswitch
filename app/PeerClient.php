<?php
declare(strict_types=1);

namespace DPS;

/**
 * Signed HTTP calls to another node's node-api.php.
 *
 * The signed path-and-query is taken from the URL that is sent, so it is the
 * same string NodeApi verifies (the server's REQUEST_URI).
 *
 * A 2xx answer is used only when it is signed by the peer named in the
 * cluster, over the nonce this call sent and the exact bytes received.
 * Error answers are never read, so they need no signature.
 */
final class PeerClient
{
    /** @var callable(string, string, array, string, int=): array{0: int, 1: string, 2: array<string, string>} lower-case header names */
    private $http;

    public function __construct(private Store $store, ?callable $http = null)
    {
        $this->http = $http ?? self::curl(...);
    }

    public function get(array $peer, string $a, array $query = []): array
    {
        return $this->decode($peer, ...$this->send($peer, 'GET', $a, $query, '', 20));
    }

    public function post(array $peer, string $a, array $body): array
    {
        return $this->decode($peer, ...$this->send($peer, 'POST', $a, [], json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 20));
    }

    /** Fetch a binary answer into $toFile. Nothing is left behind on failure. */
    public function download(array $peer, string $a, array $query, string $toFile): void
    {
        [$code, $raw, $headers, $nonce] = $this->send($peer, 'GET', $a, $query, '', 120);
        if ($code < 200 || $code > 299) {
            throw new \RuntimeException(($peer['name'] ?? 'peer') . ": HTTP $code");
        }
        $this->verifyAnswer($peer, $code, $raw, $headers, $nonce);
        $tmp = $toFile . '.part';
        if (file_put_contents($tmp, $raw) !== strlen($raw) || !rename($tmp, $toFile)) {
            @unlink($tmp);
            throw new \RuntimeException(($peer['name'] ?? 'peer') . ': could not write the download');
        }
    }

    /** @return array{0: int, 1: string, 2: array<string, string>, 3: string} code, body, headers, the nonce sent */
    private function send(array $peer, string $method, string $a, array $query, string $body, int $timeout): array
    {
        $url = rtrim((string)($peer['url'] ?? ''), '/') . '/node-api.php?' . http_build_query(['a' => $a] + $query);
        $pathQuery = (string)parse_url($url, PHP_URL_PATH) . '?' . (string)parse_url($url, PHP_URL_QUERY);
        $time = Clock::now();
        $nonce = bin2hex(random_bytes(8));
        $identity = Node::publicIdentity($this->store);
        if ($identity === null) {
            throw new \RuntimeException('This node has no identity.');
        }
        $headers = [
            'X-DPS-Node: ' . $identity['id'],
            'X-DPS-Time: ' . $time,
            'X-DPS-Nonce: ' . $nonce,
            'X-DPS-Signature: ' . Node::sign($this->store, Node::canonical($method, $pathQuery, $body, $time, $nonce)),
        ];
        if ($method === 'POST') {
            $headers[] = 'Content-Type: application/json';
        }
        $out = ($this->http)($method, $url, $headers, $body, $timeout);
        return [(int)$out[0], (string)$out[1], is_array($out[2] ?? null) ? $out[2] : [], $nonce];
    }

    /** Throws unless the answer is signed by this peer over this request's nonce and these bytes. */
    private function verifyAnswer(array $peer, int $code, string $raw, array $headers, string $nonce): void
    {
        $from = $headers['x-dps-node'] ?? null;
        $sig = $headers['x-dps-signature'] ?? null;
        $id = $peer['id'] ?? null;
        if (!is_string($id) || $id === '' || !is_string($from) || !hash_equals($id, $from) || !is_string($sig)
            || !Node::verify((string)($peer['sign_pk'] ?? ''), NodeApi::responseCanonical($nonce, $code, hash('sha256', $raw)), $sig)) {
            throw new \RuntimeException(($peer['name'] ?? 'peer') . ': bad response signature');
        }
    }

    private function decode(array $peer, int $code, string $raw, array $headers, string $nonce): array
    {
        $name = (string)($peer['name'] ?? 'peer');
        if ($code < 200 || $code > 299) {
            throw new \RuntimeException("$name: HTTP $code");
        }
        $this->verifyAnswer($peer, $code, $raw, $headers, $nonce);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \RuntimeException("$name: invalid answer");
        }
        return $data;
    }

    /** @return array{0: int, 1: string, 2: array<string, string>} */
    private static function curl(string $method, string $url, array $headers, string $body, int $timeout = 20): array
    {
        $ch = curl_init($url);
        $got = [];
        curl_setopt_array($ch, [
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$got): int {
                if (str_starts_with($line, 'HTTP/')) {
                    $got = []; // a new status line (after 100 Continue) starts a new set
                } elseif (str_contains($line, ':')) {
                    [$k, $v] = explode(':', $line, 2);
                    $got[strtolower(trim($k))] = trim($v);
                }
                return strlen($line);
            },
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $timeout,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $out = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($out === false) {
            throw new \RuntimeException('Request failed: ' . $err);
        }
        return [$code, (string)$out, $got];
    }
}
