<?php
declare(strict_types=1);

namespace DPS;

/**
 * The shared node list (data/cluster.json): who the nodes are, their rank,
 * the primary, and the settings every node agrees on.
 */
final class Cluster
{
    private const DOC = 'cluster';

    public static function read(Store $store): array
    {
        $c = $store->read(self::DOC);
        return [
            'version' => (int)($c['version'] ?? 0),
            'k' => (int)($c['k'] ?? 1),
            'primary' => isset($c['primary']) && is_string($c['primary']) ? $c['primary'] : null,
            'isolation_hours' => (int)($c['isolation_hours'] ?? 48),
            'step_delay_min' => (int)($c['step_delay_min'] ?? 30),
            'nodes' => array_values(is_array($c['nodes'] ?? null) ? $c['nodes'] : []),
        ];
    }

    /** Validates and stores the cluster; the version becomes the stored one plus 1. */
    public static function save(Store $store, array $cluster): void
    {
        $store->locked(function () use ($store, $cluster): void {
            $clean = self::validate($cluster);
            $clean['version'] = self::read($store)['version'] + 1;
            $store->write(self::DOC, $clean);
        });
    }

    public static function enabled(Store $store): bool
    {
        return count(self::read($store)['nodes']) >= 2;
    }

    public static function find(Store $store, string $nodeId): ?array
    {
        foreach (self::read($store)['nodes'] as $n) {
            if (($n['id'] ?? null) === $nodeId) {
                return $n;
            }
        }
        return null;
    }

    public static function selfEntry(Store $store, string $nodeId): ?array
    {
        return self::find($store, $nodeId);
    }

    /** @return list<array> every node except $nodeId */
    public static function peers(Store $store, string $nodeId): array
    {
        return array_values(array_filter(
            self::read($store)['nodes'],
            static fn(array $n): bool => ($n['id'] ?? null) !== $nodeId
        ));
    }

    public static function rank(Store $store, string $nodeId): int
    {
        $n = self::find($store, $nodeId);
        return $n === null ? 0 : (int)$n['rank'];
    }

    /** True when there is no primary set, or it is this node. */
    public static function isPrimary(Store $store, string $nodeId): bool
    {
        $p = self::read($store)['primary'];
        return $p === null || $p === $nodeId;
    }

    /** @return list<string> unique scheme://host[:port] of every node's public_url */
    public static function publicOrigins(Store $store): array
    {
        $out = [];
        foreach (self::read($store)['nodes'] as $n) {
            $pub = $n['public_url'] ?? null;
            $o = is_string($pub) ? self::originOf($pub) : null;
            if ($o !== null && !in_array($o, $out, true)) {
                $out[] = $o;
            }
        }
        return $out;
    }

    /** The origin of a public URL, or null when the URL is not acceptable. */
    private static function originOf(string $url): ?string
    {
        if (!filter_var($url, FILTER_VALIDATE_URL) || str_contains($url, '@') || str_contains($url, '?') || str_contains($url, '#')) {
            return null;
        }
        $p = parse_url($url);
        if (!is_array($p) || isset($p['user']) || isset($p['pass']) || isset($p['query']) || isset($p['fragment'])) {
            return null;
        }
        $scheme = strtolower((string)($p['scheme'] ?? ''));
        $host = strtolower((string)($p['host'] ?? ''));
        if ($host === '' || !preg_match('/^[a-z0-9.-]+$/D', $host)) {
            return null;
        }
        if ($scheme !== 'https' && !($scheme === 'http' && getenv('DPS_TEST_ALLOW_HTTP_PUBLIC') === '1')) {
            return null;
        }
        return $scheme . '://' . $host . (isset($p['port']) ? ':' . $p['port'] : '');
    }

    private static function validate(array $c): array
    {
        $nodes = [];
        $ids = [];
        $ranks = [];
        foreach (array_values($c['nodes'] ?? []) as $n) {
            $n = is_array($n) ? $n : [];
            $id = $n['id'] ?? null;
            if (!is_string($id) || !preg_match('/^n_[0-9a-f]{8}$/D', $id)) {
                throw new \InvalidArgumentException('Node id must be n_ followed by 8 hex characters.');
            }
            if (isset($ids[$id])) {
                throw new \InvalidArgumentException("Node $id is listed twice.");
            }
            $ids[$id] = true;
            $name = $n['name'] ?? null;
            if (!is_string($name) || mb_strlen($name) < 1 || mb_strlen($name) > 40 || preg_match('/[\x00-\x1f\x7f]/', $name)) {
                throw new \InvalidArgumentException('Node name must be 1 to 40 characters without control characters.');
            }
            $url = $n['url'] ?? null;
            if (!is_string($url) || !self::urlOk($url)) {
                throw new \InvalidArgumentException('Node URL must be https, or http on a private or Tailscale address.');
            }
            if (!self::b64Len($n['sign_pk'] ?? null, 32)) {
                throw new \InvalidArgumentException('sign_pk must be 32 bytes in base64url.');
            }
            $sh = $n['share_pk'] ?? null;
            if (!is_string($sh) || $sh === '' || strlen($sh) > 400 || !preg_match('/^[A-Za-z0-9_-]+$/D', $sh)) {
                throw new \InvalidArgumentException('share_pk must be non-empty base64url, at most 400 characters.');
            }
            $rank = $n['rank'] ?? null;
            if (!is_int($rank) || $rank < 0 || $rank > 9) {
                throw new \InvalidArgumentException('Rank must be a whole number from 0 to 9.');
            }
            if (isset($ranks[$rank])) {
                throw new \InvalidArgumentException("Rank $rank is used twice.");
            }
            $ranks[$rank] = true;
            $entry = ['id' => $id, 'name' => $name, 'url' => $url, 'sign_pk' => $n['sign_pk'], 'share_pk' => $sh, 'rank' => $rank];
            $pub = $n['public_url'] ?? null;
            if ($pub !== null && $pub !== '') {
                if (!is_string($pub) || self::originOf($pub) === null) {
                    throw new \InvalidArgumentException('Public address must be https, without a user name, query or fragment.');
                }
                $entry['public_url'] = $pub;
            }
            $nodes[] = $entry;
        }
        $k = $c['k'] ?? 1;
        if (!is_int($k) || $k < 1 || $k > max(1, count($nodes))) {
            throw new \InvalidArgumentException('k must be between 1 and the number of nodes.');
        }
        $primary = $c['primary'] ?? null;
        if ($primary !== null && (!is_string($primary) || !isset($ids[$primary]))) {
            throw new \InvalidArgumentException('The primary must be one of the nodes.');
        }
        $iso = $c['isolation_hours'] ?? 48;
        if (!is_int($iso) || $iso < 12 || $iso > 168) {
            throw new \InvalidArgumentException('isolation_hours must be 12 to 168.');
        }
        $delay = $c['step_delay_min'] ?? 30;
        if (!is_int($delay) || $delay < 15 || $delay > 240) {
            throw new \InvalidArgumentException('step_delay_min must be 15 to 240.');
        }
        return ['version' => 0, 'k' => $k, 'primary' => $primary, 'isolation_hours' => $iso,
            'step_delay_min' => $delay, 'nodes' => $nodes];
    }

    private static function b64Len(mixed $v, int $bytes): bool
    {
        if (!is_string($v) || !preg_match('/^[A-Za-z0-9_-]+$/D', $v)) {
            return false;
        }
        $raw = base64_decode(strtr($v, '-_', '+/'), true);
        return $raw !== false && strlen($raw) === $bytes;
    }

    private static function urlOk(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        $parts = parse_url($url);
        if (!is_array($parts) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || str_contains($url, '@') || str_contains($url, '?') || str_contains($url, '#')) {
            return false;
        }
        if ($scheme === 'https') {
            return $host !== '';
        }
        if ($scheme !== 'http' || $host === '') {
            return false;
        }
        if (str_ends_with($host, '.local') || str_ends_with($host, '.ts.net')) {
            return true;
        }
        $ip = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        if ($ip === false) {
            return false;
        }
        $l = ip2long($ip);
        foreach ([['10.0.0.0', 8], ['172.16.0.0', 12], ['192.168.0.0', 16], ['100.64.0.0', 10], ['127.0.0.0', 8]] as [$net, $bits]) {
            $mask = -1 << (32 - $bits);
            if (($l & $mask) === (ip2long($net) & $mask)) {
                return true;
            }
        }
        return false;
    }
}
