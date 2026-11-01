<?php
declare(strict_types=1);

namespace DPS;

/**
 * The vault's HTTP rules, free of any web globals so tests can call it.
 * vault-api.php builds the request and sends the answer.
 *
 * Request: {method, a, query, body (raw string), owner, csrf, released}
 * Answer:  {status, json?, file?}
 */
final class VaultApi
{
    private const BODY_MAX = 4194304 + 64;

    /** route => [who, needs csrf]. who: any | owner | released | owner_or_released */
    private const ROUTES = [
        'GET status' => ['any', false],
        'GET owner' => ['owner', false],
        'POST init' => ['owner', true],
        'POST recipient' => ['owner', true],
        'POST recipient_delete' => ['owner', true],
        'POST package' => ['owner', true],
        'POST package_delete' => ['owner', true],
        'PUT chunk' => ['owner', true],
        'GET chunk' => ['owner_or_released', false],
        'GET public' => ['released', false],
        'GET release' => ['released', false],
    ];

    public function __construct(private Vault $vault)
    {
    }

    public function handle(array $req): array
    {
        $method = is_string($req['method'] ?? null) ? strtoupper($req['method']) : '';
        $a = is_string($req['a'] ?? null) ? $req['a'] : '';
        $route = self::ROUTES["$method $a"] ?? null;
        if ($route === null) {
            return $this->err(404, 'not found');
        }
        [$who, $needsCsrf] = $route;
        $owner = ($req['owner'] ?? false) === true;
        $released = ($req['released'] ?? false) === true;

        if ($who === 'owner') {
            if (!$owner) {
                return $this->err(401, 'sign in required');
            }
            if ($needsCsrf && ($req['csrf'] ?? false) !== true) {
                return $this->err(403, 'bad token');
            }
        } elseif ($who === 'released' && !$released) {
            return $this->err(403, 'not released');
        } elseif ($who === 'owner_or_released' && !$owner && !$released) {
            return $this->err(403, 'not released');
        }

        try {
            return $this->dispatch("$method $a", $req, $released);
        } catch (\InvalidArgumentException $e) {
            return $this->err(400, $e->getMessage());
        } catch (\LengthException $e) {
            return $this->err(413, $e->getMessage());
        } catch (\Throwable $e) {
            try {
                store()->log('vault.error', 'Vault API error: ' . get_class($e) . ': ' . $e->getMessage());
            } catch (\Throwable) {
            }
            return $this->err(500, 'server error');
        }
    }

    private function dispatch(string $route, array $req, bool $released): array
    {
        $v = $this->vault;
        switch ($route) {
            case 'GET status':
                return $this->ok(['initialised' => $v->read()['owner'] !== null, 'released' => $released]);
            case 'GET owner':
                return $this->ok($v->read());
            case 'POST init':
                $b = $this->json($req);
                if (!is_array($b['owner'] ?? null) || !is_string($b['rk'] ?? null)) {
                    throw new \InvalidArgumentException('owner and rk are required.');
                }
                $v->initialise($b['owner'], $b['rk']);
                return $this->ok(['ok' => true]);
            case 'POST recipient':
                $v->saveRecipient($this->json($req));
                return $this->ok(['ok' => true]);
            case 'POST recipient_delete':
                $v->deleteRecipient($this->idOf($this->json($req)));
                return $this->ok(['ok' => true]);
            case 'POST package':
                $v->savePackage($this->json($req));
                $v->gc();
                return $this->ok(['ok' => true]);
            case 'POST package_delete':
                $v->deletePackage($this->idOf($this->json($req)));
                return $this->ok(['ok' => true]);
            case 'PUT chunk':
                [$file, $n] = $this->chunkArgs($req);
                $body = $req['body'] ?? '';
                if (!is_string($body)) {
                    throw new \InvalidArgumentException('The body must be raw bytes.');
                }
                $v->putChunk($file, $n, $body);
                return $this->ok(['ok' => true]);
            case 'GET chunk':
                [$file, $n] = $this->chunkArgs($req);
                $path = $v->chunkPath($file, $n);
                return $path === null ? $this->err(404, 'not found') : ['status' => 200, 'file' => $path];
            case 'GET public':
                return $this->ok($v->publicView());
            case 'GET release':
                $rk = $v->releaseKey();
                return $rk === null ? $this->err(404, 'not found') : $this->ok(['rk' => $rk]);
        }
        return $this->err(404, 'not found');
    }

    private function json(array $req): array
    {
        $raw = $req['body'] ?? '';
        if (!is_string($raw) || strlen($raw) > self::BODY_MAX) {
            throw new \LengthException('The body is too large.');
        }
        try {
            $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \InvalidArgumentException('The body is not valid JSON.');
        }
        if (!is_array($data)) {
            throw new \InvalidArgumentException('The body must be a JSON object.');
        }
        return $data;
    }

    private function idOf(array $b): string
    {
        if (!is_string($b['id'] ?? null)) {
            throw new \InvalidArgumentException('An id is required.');
        }
        return $b['id'];
    }

    /** @return array{0: string, 1: int} */
    private function chunkArgs(array $req): array
    {
        $q = is_array($req['query'] ?? null) ? $req['query'] : [];
        $file = $q['file'] ?? null;
        $n = $q['n'] ?? null;
        if (!is_string($file) || !is_string($n) || !preg_match('/^(0|[1-9][0-9]{0,4})$/', $n)) {
            throw new \InvalidArgumentException('file and n are required.');
        }
        return [$file, (int) $n];
    }

    private function ok(array $json): array
    {
        return ['status' => 200, 'json' => $json];
    }

    private function err(int $status, string $message): array
    {
        return ['status' => $status, 'json' => ['error' => $message]];
    }
}
