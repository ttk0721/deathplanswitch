<?php
declare(strict_types=1);

namespace DPS;

/**
 * This node's identity: an Ed25519 key to sign requests and a P-256 key
 * that peers use to send key shares. No networking here.
 */
final class Node
{
    public static function init(Store $store, string $name): array
    {
        return $store->locked(function () use ($store, $name): array {
            if ($store->exists('node')) {
                throw new \RuntimeException('This node already has an identity.');
            }
            $name = trim($name);
            if ($name === '' || strlen($name) > 60) {
                throw new \InvalidArgumentException('The node name must be 1 to 60 characters.');
            }
            $pair = sodium_crypto_sign_keypair();
            $ec = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
            if ($ec === false || !openssl_pkey_export($ec, $pem)) {
                throw new \RuntimeException('Could not create the share key.');
            }
            $details = openssl_pkey_get_details($ec);
            $spki = is_array($details) ? (string)($details['key'] ?? '') : '';
            $der = base64_decode((string)preg_replace('/-----[A-Z ]+-----|\s+/', '', $spki), true);
            if ($der === false || $der === '') {
                throw new \RuntimeException('Could not create the share key.');
            }
            $data = [
                'id' => 'n_' . bin2hex(random_bytes(4)),
                'name' => $name,
                'sign_sk' => self::b64(sodium_crypto_sign_secretkey($pair)),
                'sign_pk' => self::b64(sodium_crypto_sign_publickey($pair)),
                'share_key_pem' => $pem,
                'share_pk' => self::b64((string)$der),
            ];
            $store->write('node', $data);
            @chmod($store->path('node'), 0600);
            return self::publicOf($data);
        });
    }

    public static function publicIdentity(Store $store): ?array
    {
        $d = $store->read('node');
        return isset($d['id'], $d['sign_pk']) ? self::publicOf($d) : null;
    }

    public static function canonical(string $method, string $pathQuery, string $body, int $time, string $nonce): string
    {
        return "DPS1\n" . $method . "\n" . $pathQuery . "\n" . $time . "\n" . $nonce . "\n" . hash('sha256', $body);
    }

    public static function sign(Store $store, string $canonical): string
    {
        $sk = self::unb64((string)($store->read('node')['sign_sk'] ?? ''));
        if ($sk === null || strlen($sk) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new \RuntimeException('This node has no signing key.');
        }
        return self::b64(sodium_crypto_sign_detached($canonical, $sk));
    }

    public static function verify(string $signPk, string $canonical, string $signature): bool
    {
        $pk = self::unb64($signPk);
        $sig = self::unb64($signature);
        if ($pk === null || $sig === null
            || strlen($pk) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }
        try {
            return sodium_crypto_sign_verify_detached($sig, $canonical, $pk);
        } catch (\Throwable) {
            return false;
        }
    }

    /** Records the nonce; true when it was already seen. Entries older than 600 s are dropped. */
    public static function seenNonce(Store $store, string $nodeId, string $nonce, int $now): bool
    {
        return $store->locked(function () use ($store, $nodeId, $nonce, $now): bool {
            $all = $store->read('nonces');
            foreach ($all as $k => $t) {
                if (!is_int($t) || $t <= $now - 600) {
                    unset($all[$k]);
                }
            }
            $key = $nodeId . ':' . $nonce;
            $seen = isset($all[$key]);
            if (!$seen) {
                $all[$key] = $now;
            }
            $store->write('nonces', $all);
            return $seen;
        });
    }

    private static function publicOf(array $d): array
    {
        return ['id' => $d['id'], 'name' => $d['name'], 'sign_pk' => $d['sign_pk'], 'share_pk' => $d['share_pk']];
    }

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function unb64(string $s): ?string
    {
        if ($s === '' || !preg_match('/^[A-Za-z0-9_-]+$/D', $s)) {
            return null;
        }
        $r = base64_decode(strtr($s, '-_', '+/'), true);
        return $r === false ? null : $r;
    }
}
