<?php
declare(strict_types=1);

namespace DPS;

/**
 * Opaque storage for the end-to-end encrypted vault. The server only checks
 * shapes and sizes; it never holds a key that opens anything.
 */
final class Vault
{
    public const CHUNK_MAX = 4194304 + 64;
    private const ID = '/^[rpf]_[0-9a-f]{16}$/';
    private const B64 = '/^[A-Za-z0-9_-]*$/';

    public function __construct(private Store $store)
    {
    }

    public function read(): array
    {
        return $this->store->read('vault', ['version' => 1, 'owner' => null, 'recipients' => [], 'packages' => []]);
    }

    public function initialise(array $owner, string $rk): void
    {
        $o = [
            'salt' => $this->b64($owner['salt'] ?? null, 16, 'owner salt'),
            'iterations' => $this->iterations($owner['iterations'] ?? null),
            'wrapped_rk' => $this->sealed($owner['wrapped_rk'] ?? null, 'wrapped_rk'),
            'check' => $this->sealed($owner['check'] ?? null, 'check'),
        ];
        if ($rk === '' || strlen($rk) > 200 || !preg_match(self::B64, $rk)) {
            throw new \InvalidArgumentException('The release key is not valid.');
        }
        $this->store->locked(function () use ($o, $rk): void {
            $v = $this->read();
            if ($v['owner'] !== null) {
                throw new \InvalidArgumentException('The vault is already set up.');
            }
            $v['owner'] = $o;
            $this->store->write('vault', $v);
            $this->store->write('release', ['rk' => $rk]);
        });
    }

    public function saveRecipient(array $r): void
    {
        $this->store->locked(function () use ($r): void {
            // Opening a package needs a code handed over in advance, so a contact who is not to be told cannot hold one.
            // Checked under the lock, so a contact made silent a moment ago is seen.
            $contactId = $this->contactId($r['contact_id'] ?? null);
            $contact = $contactId === null ? null : (new Contacts($this->store))->find($contactId);
            if ($contact !== null && Contacts::isSilent($contact)) {
                throw new \InvalidArgumentException(t('{name} is set not to be told in advance, so they cannot hold vault packages. Turn that option off on their contact page first.', ['name' => (string) $contact['name']]));
            }
            $clean = [
                'id' => $this->id($r['id'] ?? null, 'r'),
                'contact_id' => $contactId,
                'label' => $this->label($r['label'] ?? null),
                'code' => $this->box($r['code'] ?? null, false, 'code'),
                'password' => ($r['password'] ?? null) === null ? null : $this->box($r['password'], true, 'password'),
                'owner_wrapped' => $this->sealed($r['owner_wrapped'] ?? null, 'owner_wrapped'),
            ];
            $v = $this->read();
            $found = false;
            foreach ($v['recipients'] as $i => $existing) {
                if ($existing['id'] === $clean['id']) {
                    $v['recipients'][$i] = $clean;
                    $found = true;
                }
            }
            if (!$found) {
                if (count($v['recipients']) >= 50) {
                    throw new \InvalidArgumentException('At most 50 recipients are allowed.');
                }
                $v['recipients'][] = $clean;
            }
            $this->store->write('vault', $v);
        });
    }

    public function deleteRecipient(string $id): void
    {
        $this->id($id, 'r');
        $this->store->locked(function () use ($id): void {
            $v = $this->read();
            $v['recipients'] = array_values(array_filter($v['recipients'], fn($r) => $r['id'] !== $id));
            $invites = $this->store->read('vault-invites', []);
            if (isset($invites[$id])) {
                unset($invites[$id]);
                $this->store->write('vault-invites', $invites);
            }
            foreach ($v['packages'] as &$p) {
                $p['recipients'] = array_values(array_filter($p['recipients'], fn($x) => $x !== $id));
                unset($p['wraps'][$id]);
            }
            unset($p);
            $this->store->write('vault', $v);
        });
    }

    /** Issues a single-use invite token for a recipient; a new one replaces the old. Returns the plain token. */
    public function issueInvite(string $recipientId): string
    {
        $this->id($recipientId, 'r');
        return $this->store->locked(function () use ($recipientId): string {
            if (!in_array($recipientId, array_column($this->read()['recipients'], 'id'), true)) {
                throw new \InvalidArgumentException('Unknown recipient.');
            }
            [$plain, $record] = Tokens::issue(Schedule::CONFIRM_TOKEN_TTL);
            $invites = $this->store->read('vault-invites', []);
            $invites[$recipientId] = $record;
            $this->store->write('vault-invites', $invites);
            return $plain;
        });
    }

    /** The recipient an unexpired invite token belongs to: only its id and code wrap. */
    public function inviteRecipient(string $token): ?array
    {
        $rid = $this->inviteOwner($token);
        if ($rid === null) {
            return null;
        }
        foreach ($this->read()['recipients'] as $r) {
            if ($r['id'] === $rid) {
                return ['id' => $r['id'], 'code' => $r['code']];
            }
        }
        return null;
    }

    /** Sets the recipient's password wrap and consumes the token. */
    public function setPassword(string $token, array $password): void
    {
        $box = $this->box($password, true, 'password');
        if ($box['iterations'] < 100000 || $box['iterations'] > 20000000) {
            throw new \InvalidArgumentException('Iterations must be 100000 to 20000000.');
        }
        $this->store->locked(function () use ($token, $box): void {
            $rid = $this->inviteOwner($token);
            $v = $this->read();
            $done = false;
            foreach ($v['recipients'] as $i => $r) {
                if ($rid !== null && $r['id'] === $rid) {
                    $v['recipients'][$i]['password'] = $box;
                    $done = true;
                }
            }
            if (!$done) {
                throw new \InvalidArgumentException('The invite is not valid.');
            }
            $this->store->write('vault', $v);
            $invites = $this->store->read('vault-invites', []);
            unset($invites[$rid]);
            $this->store->write('vault-invites', $invites);
        });
    }

    private function inviteOwner(string $token): ?string
    {
        $found = null;
        foreach ($this->store->read('vault-invites', []) as $rid => $record) {
            if (Tokens::matches($token, [$record])) {
                $found = (string) $rid;
            }
        }
        return $found;
    }

    public function savePackage(array $p): void
    {
        $id = $this->id($p['id'] ?? null, 'p');
        $label = $this->label($p['label'] ?? null);
        $rids = $p['recipients'] ?? null;
        if (!is_array($rids) || !array_is_list($rids) || count($rids) > 50) {
            throw new \InvalidArgumentException('Recipients must be a list of at most 50.');
        }
        foreach ($rids as $rid) {
            $this->id($rid, 'r');
        }
        $rids = array_values(array_unique($rids));
        $wrapsIn = $p['wraps'] ?? null;
        if (!is_array($wrapsIn)) {
            throw new \InvalidArgumentException('Wraps must be an object.');
        }
        $wraps = [];
        foreach ($wrapsIn as $rid => $w) {
            if (!in_array((string) $rid, $rids, true)) {
                throw new \InvalidArgumentException('A wrap belongs to a recipient that is not on the package.');
            }
            $wraps[(string) $rid] = $this->sealed($w, 'wrap');
        }
        $manifest = $p['manifest'] ?? null;
        if ($manifest !== null) {
            $manifest = $this->sealed($manifest, 'manifest', 2_000_000);
        }
        $filesIn = $p['files'] ?? null;
        if (!is_array($filesIn) || !array_is_list($filesIn) || count($filesIn) > 2000) {
            throw new \InvalidArgumentException('Files must be a list of at most 2000.');
        }
        $files = [];
        foreach ($filesIn as $f) {
            if (!is_array($f)) {
                throw new \InvalidArgumentException('A file entry is not valid.');
            }
            $chunks = $f['chunks'] ?? null;
            $size = $f['size'] ?? null;
            if (!is_int($chunks) || $chunks < 1 || $chunks > 100000) {
                throw new \InvalidArgumentException('A file needs 1 to 100000 chunks.');
            }
            if (!is_int($size) || $size < 0) {
                throw new \InvalidArgumentException('A file size must be zero or more.');
            }
            $files[] = ['id' => $this->id($f['id'] ?? null, 'f'), 'chunks' => $chunks, 'size' => $size];
        }
        $clean = [
            'id' => $id,
            'label' => $label,
            'recipients' => $rids,
            'owner_wrapped_ck' => $this->sealed($p['owner_wrapped_ck'] ?? null, 'owner_wrapped_ck'),
            'wraps' => $wraps,
            'manifest' => $manifest,
            'files' => $files,
        ];
        $this->store->locked(function () use ($clean): void {
            $v = $this->read();
            $known = array_column($v['recipients'], 'id');
            foreach ($clean['recipients'] as $rid) {
                if (!in_array($rid, $known, true)) {
                    throw new \InvalidArgumentException('Unknown recipient ' . $rid . '.');
                }
            }
            $found = false;
            foreach ($v['packages'] as $i => $existing) {
                if ($existing['id'] === $clean['id']) {
                    $v['packages'][$i] = $clean;
                    $found = true;
                }
            }
            if (!$found) {
                if (count($v['packages']) >= 200) {
                    throw new \InvalidArgumentException('At most 200 packages are allowed.');
                }
                $v['packages'][] = $clean;
            }
            $this->store->write('vault', $v);
        });
    }

    public function deletePackage(string $id): void
    {
        $this->id($id, 'p');
        $this->store->locked(function () use ($id): void {
            $v = $this->read();
            $files = [];
            $keep = [];
            foreach ($v['packages'] as $p) {
                if ($p['id'] === $id) {
                    $files = array_column($p['files'], 'id');
                } else {
                    $keep[] = $p;
                }
            }
            $v['packages'] = $keep;
            $this->store->write('vault', $v);
            foreach ($files as $fid) {
                $this->removeDir($this->blobRoot() . '/' . $fid);
            }
        });
    }

    public function putChunk(string $fileId, int $n, string $bytes): void
    {
        $this->id($fileId, 'f');
        if ($n < 0 || $n >= 100000) {
            throw new \InvalidArgumentException('The chunk number is out of range.');
        }
        if (strlen($bytes) > self::CHUNK_MAX) {
            throw new \LengthException('The chunk is too large.');
        }
        $this->store->locked(function () use ($fileId, $n, $bytes): void {
            $limit = (int) (getenv('DPS_VAULT_MAX_BYTES') ?: 21474836480);
            $dir = $this->blobRoot() . '/' . $fileId;
            $target = $dir . '/' . $n . '.bin';
            $used = $this->usedBytes() - (is_file($target) ? $this->cost((int) filesize($target)) : 0);
            if ($used + $this->cost(strlen($bytes)) > $limit) {
                throw new \LengthException('The vault is full.');
            }
            if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
                throw new \RuntimeException('Cannot create the vault folder.');
            }
            $tmp = $dir . '/' . $n . '.tmp.' . bin2hex(random_bytes(6));
            if (@file_put_contents($tmp, $bytes) !== strlen($bytes)) {
                @unlink($tmp);
                throw new \RuntimeException('Could not write the chunk.');
            }
            @chmod($tmp, 0600);
            if (!@rename($tmp, $target)) {
                @unlink($tmp);
                throw new \RuntimeException('Could not store the chunk.');
            }
        });
    }

    public function chunkPath(string $fileId, int $n): ?string
    {
        $this->id($fileId, 'f');
        if ($n < 0 || $n >= 100000) {
            return null;
        }
        $path = $this->blobRoot() . '/' . $fileId . '/' . $n . '.bin';
        return is_file($path) ? $path : null;
    }

    public function publicView(): array
    {
        $v = $this->read();
        $recipients = [];
        foreach ($v['recipients'] as $r) {
            $recipients[] = ['id' => $r['id'], 'code' => $r['code'], 'password' => $r['password']];
        }
        $packages = [];
        foreach ($v['packages'] as $p) {
            $packages[] = [
                'id' => $p['id'],
                'label' => $p['label'],
                'recipients' => $p['recipients'],
                'wraps' => (object) $p['wraps'],
                'manifest' => $p['manifest'],
                'files' => $p['files'],
            ];
        }
        return ['recipients' => $recipients, 'packages' => $packages];
    }

    public function shares(): ?array
    {
        $s = $this->read()['shares'] ?? null;
        return is_array($s) ? $s : null;
    }

    /** Stores the key-share boxes and deletes the plain release key. */
    public function saveShares(array $in): void
    {
        $k = $in['k'] ?? null;
        $n = $in['n'] ?? null;
        $cv = $in['cluster_version'] ?? null;
        $boxesIn = $in['boxes'] ?? null;
        if (!is_int($n) || !is_int($k) || $k < 1 || $n > 9 || $k > $n) {
            throw new \InvalidArgumentException('Shares need 1 <= k <= n <= 9.');
        }
        if (!is_int($cv) || $cv < 0) {
            throw new \InvalidArgumentException('The cluster version is not valid.');
        }
        if (!is_array($boxesIn) || count($boxesIn) !== $n) {
            throw new \InvalidArgumentException('There must be one box per share.');
        }
        $boxes = [];
        foreach ($boxesIn as $nodeId => $box) {
            if (!preg_match('/^n_[0-9a-f]{8}$/D', (string) $nodeId) || !is_array($box)) {
                throw new \InvalidArgumentException('A box must belong to a node id.');
            }
            $clean = [
                'epk' => $this->b64($box['epk'] ?? null, null, 'epk', 300),
                'iv' => $this->b64($box['iv'] ?? null, 12, 'share iv'),
                'ct' => $this->b64($box['ct'] ?? null, null, 'share ct', 200),
            ];
            if (strlen($clean['epk']) < 80 || strlen($clean['ct']) < 24) {
                throw new \InvalidArgumentException('A box is too short.');
            }
            $boxes[(string) $nodeId] = $clean;
        }
        $this->store->locked(function () use ($k, $n, $cv, $boxes): void {
            $v = $this->read();
            $version = (int) ($v['shares']['version'] ?? 0) + 1;
            $v['shares'] = ['version' => $version, 'k' => $k, 'n' => $n, 'cluster_version' => $cv, 'boxes' => $boxes];
            $this->store->write('vault', $v);
            $path = $this->store->path('release');
            @unlink($path);
            @unlink($path . '.bak');
            clearstatcache();
            if (file_exists($path) || file_exists($path . '.bak')) {
                throw new \RuntimeException('The plain release key could not be deleted.');
            }
        });
    }

    /** This node's share as {x, y: b64url}, or null when it has none or cannot open it. */
    public function myShare(string $nodeId): ?array
    {
        $box = $this->shares()['boxes'][$nodeId] ?? null;
        if (!is_array($box)) {
            return null;
        }
        try {
            $raw = ShareBox::open($this->store, $nodeId, $box);
        } catch (\Throwable) {
            return null;
        }
        if (strlen($raw) !== 33) {
            return null;
        }
        return ['x' => ord($raw[0]), 'y' => rtrim(strtr(base64_encode(substr($raw, 1)), '+/', '-_'), '=')];
    }

    public function releaseKey(): ?string
    {
        $rk = $this->store->read('release', [])['rk'] ?? null;
        return is_string($rk) && $rk !== '' ? $rk : null;
    }

    /** Removes blob folders no package refers to and nobody touched for $olderThan seconds. */
    public function gc(int $olderThan = 86400): int
    {
        return $this->store->locked(function () use ($olderThan): int {
            $used = [];
            foreach ($this->read()['packages'] as $p) {
                foreach ($p['files'] as $f) {
                    $used[$f['id']] = true;
                }
            }
            $removed = 0;
            foreach (glob($this->blobRoot() . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
                $name = basename($dir);
                if (!preg_match(self::ID, $name) || isset($used[$name])) {
                    continue;
                }
                if (time() - (int) filemtime($dir) > $olderThan) {
                    $this->removeDir($dir);
                    $removed++;
                }
            }
            return $removed;
        });
    }

    private function blobRoot(): string
    {
        return $this->store->dir() . '/vault';
    }

    private function usedBytes(): int
    {
        $total = 0;
        // Every file counts, leftover temp files too, and none for less than
        // a disk block, so a flood of tiny chunks cannot dodge the limit.
        foreach (glob($this->blobRoot() . '/*/*') ?: [] as $f) {
            $total += $this->cost((int) filesize($f));
        }
        return $total;
    }

    private function cost(int $size): int
    {
        return max($size, 4096);
    }

    private function removeDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }

    private function id(mixed $v, string $kind): string
    {
        if (!is_string($v) || !preg_match(self::ID, $v) || $v[0] !== $kind) {
            throw new \InvalidArgumentException("Not a valid {$kind}_ id.");
        }
        return $v;
    }

    private function label(mixed $v): string
    {
        if (!is_string($v) || $v === '' || !mb_check_encoding($v, 'UTF-8') || mb_strlen($v) > 80 || preg_match('/[\x00-\x1f\x7f]/', $v)) {
            throw new \InvalidArgumentException('The label must be 1 to 80 characters with no control characters.');
        }
        return $v;
    }

    private function contactId(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        if (!is_string($v) || $v === '' || strlen($v) > 64 || !preg_match('/^[A-Za-z0-9_-]+$/', $v)) {
            throw new \InvalidArgumentException('The contact id is not valid.');
        }
        return $v;
    }

    private function iterations(mixed $v): int
    {
        if (!is_int($v) || $v < 1 || $v > 100_000_000) {
            throw new \InvalidArgumentException('Iterations must be a positive number.');
        }
        return $v;
    }

    /** A base64url string without padding; $bytes (when given) is the exact decoded length. */
    private function b64(mixed $v, ?int $bytes, string $what, int $max = 100000): string
    {
        if (!is_string($v) || $v === '' || strlen($v) > $max || !preg_match(self::B64, $v) || strlen($v) % 4 === 1) {
            throw new \InvalidArgumentException("{$what} is not valid base64url.");
        }
        if ($bytes !== null && intdiv(strlen($v) * 3, 4) !== $bytes) {
            throw new \InvalidArgumentException("{$what} must be {$bytes} bytes.");
        }
        return $v;
    }

    private function sealed(mixed $v, string $what, int $maxCt = 100000): array
    {
        if (!is_array($v)) {
            throw new \InvalidArgumentException("{$what} must be a sealed value.");
        }
        return [
            'iv' => $this->b64($v['iv'] ?? null, 12, "{$what} iv"),
            'ct' => $this->b64($v['ct'] ?? null, null, "{$what} ct", $maxCt),
        ];
    }

    private function box(mixed $v, bool $withIterations, string $what): array
    {
        if (!is_array($v)) {
            throw new \InvalidArgumentException("{$what} must be an object.");
        }
        $out = ['salt' => $this->b64($v['salt'] ?? null, 16, "{$what} salt")];
        if ($withIterations) {
            $out['iterations'] = $this->iterations($v['iterations'] ?? null);
        }
        $out['iv'] = $this->b64($v['iv'] ?? null, 12, "{$what} iv");
        $out['ct'] = $this->b64($v['ct'] ?? null, null, "{$what} ct");
        return $out;
    }
}
