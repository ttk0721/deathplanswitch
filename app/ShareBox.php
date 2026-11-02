<?php
declare(strict_types=1);

namespace DPS;

/**
 * Share boxes: ECDH-P256 + HKDF-SHA256 + AES-256-GCM. The owner's browser
 * seals, this node opens with the private key in node.json.
 * Box: {epk: b64url SPKI DER, iv: b64url(12), ct: b64url (ciphertext then 16-byte tag)}.
 */
final class ShareBox
{
    private const INFO = 'dps-share-v1';

    /** @throws \RuntimeException on any failure */
    public static function open(Store $store, string $nodeId, array $box): string
    {
        $pem = (string)($store->read('node')['share_key_pem'] ?? '');
        $epk = self::unb64($box['epk'] ?? null);
        $iv = self::unb64($box['iv'] ?? null);
        $ct = self::unb64($box['ct'] ?? null);
        if ($pem === '' || $epk === null || $iv === null || $ct === null
            || strlen($epk) < 60 || strlen($epk) > 200 || strlen($iv) !== 12
            || strlen($ct) < 17 || strlen($ct) > 1024) {
            throw new \RuntimeException('Bad share box.');
        }
        $priv = openssl_pkey_get_private($pem);
        $peer = openssl_pkey_get_public(self::pem($epk));
        if ($priv === false || $peer === false) {
            throw new \RuntimeException('Bad share box.');
        }
        $z = openssl_pkey_derive($peer, $priv, 32);
        if (!is_string($z) || strlen($z) !== 32) {
            throw new \RuntimeException('Bad share box.');
        }
        $key = hash_hkdf('sha256', $z, 32, self::INFO, $nodeId);
        $plain = openssl_decrypt(substr($ct, 0, -16), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, substr($ct, -16), $nodeId);
        if ($plain === false) {
            throw new \RuntimeException('Could not open the share box.');
        }
        return $plain;
    }

    /** Seals in the same format as the browser does. For tests only. */
    public static function sealForTests(string $sharePkB64, string $nodeId, string $bytes): array
    {
        $der = self::unb64($sharePkB64);
        $peer = $der === null ? false : openssl_pkey_get_public(self::pem($der));
        $eph = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        if ($peer === false || $eph === false) {
            throw new \RuntimeException('Could not seal.');
        }
        $z = openssl_pkey_derive($peer, $eph, 32);
        $details = openssl_pkey_get_details($eph);
        $epk = base64_decode((string)preg_replace('/-----[A-Z ]+-----|\s+/', '', (string)($details['key'] ?? '')), true);
        if (!is_string($z) || $epk === false) {
            throw new \RuntimeException('Could not seal.');
        }
        $key = hash_hkdf('sha256', $z, 32, self::INFO, $nodeId);
        $iv = random_bytes(12);
        $ct = openssl_encrypt($bytes, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, $nodeId, 16);
        if ($ct === false) {
            throw new \RuntimeException('Could not seal.');
        }
        return ['epk' => self::b64($epk), 'iv' => self::b64($iv), 'ct' => self::b64($ct . $tag)];
    }

    private static function pem(string $der): string
    {
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private static function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function unb64(mixed $s): ?string
    {
        if (!is_string($s) || $s === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $s) || strlen($s) % 4 === 1) {
            return null;
        }
        $raw = base64_decode(strtr($s, '-_', '+/'), true);
        return $raw === false ? null : $raw;
    }
}
