<?php
declare(strict_types=1);

namespace DPS;

/**
 * The node-to-node API behind node-api.php. A pure function of the request,
 * so it is tested without a web server.
 *
 * Every call is signed. The signature covers the method, the path and query
 * exactly as received (REQUEST_URI), the time, a nonce and the body hash, so
 * a caller must sign the same path-and-query string it puts on the wire.
 */
final class NodeApi
{
    private const ROUTES = [
        'GET state' => 'state',
        'POST checkin' => 'checkin',
        'POST nok' => 'nok',
        'GET bundle' => 'bundle',
        'GET blob' => 'blob',
    ];

    /** @param array<string, callable> $handlers state, checkin, nok, bundle, blob */
    public function __construct(private Store $store, private array $handlers)
    {
    }

    /** @return array{status: int, json?: array, file?: string} */
    public function handle(array $req): array
    {
        $method = strtoupper((string)($req['method'] ?? ''));
        $route = self::ROUTES[$method . ' ' . (string)($req['a'] ?? '')] ?? null;
        if ($route === null) {
            return self::err(404, 'not found');
        }
        $denied = $this->authenticate($req, $method);
        if ($denied !== null) {
            return $denied;
        }
        // The route and parameters used must be the ones that were signed.
        parse_str((string)parse_url((string)($req['pathQuery'] ?? ''), PHP_URL_QUERY), $signedQuery);
        if (($req['query'] ?? []) !== $signedQuery) {
            return self::err(400, 'bad input');
        }
        try {
            return $this->{'do' . ucfirst($route)}($req);
        } catch (\LogicException) {
            return self::err(403, 'not allowed');
        }
    }

    private function authenticate(array $req, string $method): ?array
    {
        $h = (array)($req['headers'] ?? []);
        $id = (string)($h['node'] ?? '');
        $time = (string)($h['time'] ?? '');
        $nonce = (string)($h['nonce'] ?? '');
        $sig = (string)($h['signature'] ?? '');
        if ($id === '' || $time === '' || $nonce === '' || $sig === '') {
            return self::err(401, 'not signed');
        }
        $node = Cluster::find($this->store, $id);
        if ($node === null) {
            return self::err(401, 'unknown node');
        }
        if (!preg_match('/^\d{1,12}$/D', $time) || !preg_match('/^[0-9a-f]{8,64}$/D', $nonce)) {
            return self::err(401, 'not signed');
        }
        $now = (int)($req['now'] ?? Clock::now());
        if (abs((int)$time - $now) > 300) {
            return self::err(408, 'clock out of range');
        }
        $canonical = Node::canonical($method, (string)($req['pathQuery'] ?? ''), (string)($req['body'] ?? ''), (int)$time, $nonce);
        if (!Node::verify((string)$node['sign_pk'], $canonical, $sig)) {
            return self::err(401, 'bad signature');
        }
        if (Node::seenNonce($this->store, $id, $nonce, $now)) {
            return self::err(409, 'replayed');
        }
        return null;
    }

    private function doState(array $req): array
    {
        return ['status' => 200, 'json' => ($this->handlers['state'])()];
    }

    private function doCheckin(array $req): array
    {
        $b = self::json($req);
        $at = $b['at'] ?? null;
        $via = $b['via'] ?? null;
        if (!is_int($at) || $at <= 0 || $at > $this->now($req) + 60
            || !is_string($via) || !preg_match('/^[A-Za-z0-9 ._:-]{1,40}$/D', $via)) {
            return self::err(400, 'bad input');
        }
        ($this->handlers['checkin'])($at, $via);
        return ['status' => 200, 'json' => ['ok' => true]];
    }

    private function doNok(array $req): array
    {
        $b = self::json($req);
        $at = $b['at'] ?? null;
        $answer = $b['answer'] ?? null;
        if (!is_int($at) || $at <= 0 || $at > $this->now($req) + 60 || !in_array($answer, ['fine', 'wrong'], true)) {
            return self::err(400, 'bad input');
        }
        ($this->handlers['nok'])($answer, $at);
        return ['status' => 200, 'json' => ['ok' => true]];
    }

    private function doBundle(array $req): array
    {
        $self = Node::publicIdentity($this->store);
        if ($self === null || Cluster::read($this->store)['primary'] !== $self['id']) {
            return self::err(403, 'not allowed');
        }
        $since = (string)($req['query']['since'] ?? '0');
        if (!preg_match('/^\d{1,15}$/D', $since)) {
            return self::err(400, 'bad input');
        }
        return ['status' => 200, 'json' => ($this->handlers['bundle'])((int)$since)];
    }

    private function doBlob(array $req): array
    {
        $file = $req['query']['file'] ?? null;
        $n = (string)($req['query']['n'] ?? '');
        if (!is_string($file) || !preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $file) || !preg_match('/^\d{1,9}$/D', $n)) {
            return self::err(400, 'bad input');
        }
        $path = ($this->handlers['blob'])($file, (int)$n);
        return $path === null ? self::err(404, 'not found') : ['status' => 200, 'file' => $path];
    }

    private function now(array $req): int
    {
        return (int)($req['now'] ?? Clock::now());
    }

    private static function json(array $req): array
    {
        $d = json_decode((string)($req['body'] ?? ''), true);
        return is_array($d) ? $d : [];
    }

    private static function err(int $status, string $message): array
    {
        return ['status' => $status, 'json' => ['error' => $message]];
    }
}
