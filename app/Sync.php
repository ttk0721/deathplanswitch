<?php
declare(strict_types=1);

namespace DPS;

/**
 * Keeps the nodes of a cluster in step. Run every tick by cli/sync.php.
 *
 *  1. Pull each peer's state and remember it.
 *  2. Let the engine act on what the peers did.
 *  3. Push a local check-in and a local next-of-kin answer to every peer.
 *  4. On a secondary, pull the primary's bundle, then any vault blobs still missing.
 *
 * A peer that fails is logged and skipped; nothing here throws.
 */
final class Sync
{
    private const ID = '/^[rpf]_[0-9a-f]{16}$/';
    /** Config keys a secondary keeps for itself. */
    private const LOCAL_CONFIG = ['base_url', 'tick_key', 'health_key', 'installed_at'];
    /** Config keys a bundle may carry (as written by install, settings and Setup); others are dropped. */
    private const CONFIG_KEYS = [
        'owner_name', 'owner_email', 'backup_email', 'owner_phone', 'timezone', 'slot_dow', 'slot_hour',
        'base_url', 'password_hash', 'smtp', 'tick_key', 'health_key', 'pending_email', 'landing', 'channels',
        'sms_template', 'apprise_url', 'separate_hosting_confirmed_at', 'install_step', 'installed_at', 'created_at',
    ];

    public function __construct(
        private Store $store,
        private Engine $engine,
        private PeerClient $client,
        private Coordinator $coordinator,
        private string $nodeId
    ) {
    }

    /** @return string[] log lines */
    public function run(int $budgetSeconds = 240): array
    {
        $deadline = time() + $budgetSeconds;
        $log = [];
        $peers = Cluster::peers($this->store, $this->nodeId);

        foreach ($peers as $peer) {
            try {
                $state = $this->client->get($peer, 'state');
                if (($state['node'] ?? null) !== $peer['id']) {
                    throw new \RuntimeException('the answer is from a different node');
                }
                $this->coordinator->recordPeer($peer['id'], $state, Clock::now());
                $log[] = "Read the state of {$peer['name']}.";
            } catch (\Throwable $e) {
                $log[] = "Could not read {$peer['name']}: " . $e->getMessage();
            }
        }

        try {
            foreach ($this->engine->applyRemote() as $line) {
                $log[] = $line;
            }
        } catch (\Throwable $e) {
            $log[] = 'Could not apply what the other nodes did: ' . $e->getMessage();
        }

        try {
            array_push($log, ...$this->push($peers));
        } catch (\Throwable $e) {
            $log[] = 'Could not push: ' . $e->getMessage();
        }

        $primary = $this->primaryPeer();
        if ($primary !== null) {
            try {
                array_push($log, ...$this->pullBundle($primary));
            } catch (\Throwable $e) {
                $log[] = "Could not read the bundle from {$primary['name']}: " . $e->getMessage();
            }
            try {
                array_push($log, ...$this->pullBlobs($primary, $deadline));
            } catch (\Throwable $e) {
                $log[] = "Could not copy the vault from {$primary['name']}: " . $e->getMessage();
            }
        }
        return $log;
    }

    /** The primary's cluster entry when this node is a secondary, else null. */
    private function primaryPeer(): ?array
    {
        $id = Cluster::read($this->store)['primary'];
        if ($id === null || $id === $this->nodeId) {
            return null;
        }
        return Cluster::find($this->store, $id);
    }

    /** @return string[] */
    private function push(array $peers): array
    {
        $log = [];
        $sync = $this->store->read('sync');
        $state = $this->engine->state();

        // A check-in made here. One that came from another node is not sent back.
        $at = $state['last_checkin_at'];
        $via = (string) ($state['last_checkin_via'] ?? '');
        if (is_int($at) && $at > (int) ($sync['pushed_checkin_at'] ?? 0) && !str_starts_with($via, 'another node')) {
            $ok = true;
            foreach ($peers as $peer) {
                try {
                    $this->client->post($peer, 'checkin', ['at' => $at, 'via' => preg_replace('/[^A-Za-z0-9 ._:-]/', '', $via) ?: 'node']);
                } catch (\Throwable $e) {
                    $ok = false;
                    $log[] = "Could not send the check-in to {$peer['name']}: " . $e->getMessage();
                }
            }
            if ($ok) {
                $this->saveSync(['pushed_checkin_at' => $at]);
                $log[] = 'Sent the check-in to the other nodes.';
            }
        }

        // A next-of-kin answer given here.
        $pushedNok = (int) ($sync['pushed_nok_at'] ?? 0);
        $answers = [];
        $nok = $this->engine->nodeState($this->nodeId)['cycle']['nok'] ?? null;
        if (is_array($nok) && $nok['at'] > $pushedNok) {
            $answers[] = [(string) $nok['answer'], (int) $nok['at']];
        }
        foreach ($this->store->history(50) as $row) {
            // "All fine" ends the cycle, so only the history remembers it. Answers taken from a peer carry "(via)".
            if (($row['event'] ?? '') === 'cycle.vouched' && (int) $row['at'] > $pushedNok
                && !str_contains((string) ($row['text'] ?? ''), '" (')) {
                $answers[] = ['fine', (int) $row['at']];
            }
        }
        if ($answers) {
            $ok = true;
            foreach ($answers as [$answer, $when]) {
                foreach ($peers as $peer) {
                    try {
                        $this->client->post($peer, 'nok', ['answer' => $answer, 'at' => $when]);
                    } catch (\Throwable $e) {
                        $ok = false;
                        $log[] = "Could not send the next of kin's answer to {$peer['name']}: " . $e->getMessage();
                    }
                }
            }
            if ($ok) {
                $this->saveSync(['pushed_nok_at' => max(array_column($answers, 1))]);
                $log[] = "Sent the next of kin's answer to the other nodes.";
            }
        }
        return $log;
    }

    /** @return string[] */
    private function pullBundle(array $primary): array
    {
        $since = (int) ($this->store->read('sync')['bundle_version'] ?? 0);
        $b = $this->client->get($primary, 'bundle', ['since' => (string) $since]);
        if (!empty($b['unchanged'])) {
            return [];
        }
        foreach (['version', 'config', 'contacts', 'vault', 'cluster'] as $k) {
            if (!isset($b[$k]) || ($k === 'version' ? !is_int($b[$k]) : !is_array($b[$k]))) {
                throw new \RuntimeException("the bundle has no valid $k");
            }
        }
        $config = array_intersect_key($b['config'], array_flip(self::CONFIG_KEYS));
        // A primary with no contacts file yet sends an empty document.
        $contacts = $b['contacts'] === [] ? [] : ($b['contacts']['contacts'] ?? null);
        if (!is_array($contacts) || !array_is_list($contacts)) {
            throw new \RuntimeException('the bundle has no valid contacts');
        }
        foreach ($contacts as $c) {
            if (!is_array($c)) {
                throw new \RuntimeException('the bundle has no valid contacts');
            }
        }
        $vault = self::cleanVault($b['vault']);
        $log = [];
        $this->store->locked(function () use ($b, $config, $contacts, $vault, &$log): void {
            $local = $this->store->read('config');
            foreach (self::LOCAL_CONFIG as $key) {
                if (array_key_exists($key, $local)) {
                    $config[$key] = $local[$key];
                } else {
                    unset($config[$key]);
                }
            }
            $this->store->write('config', $config);
            $this->store->write('contacts', ['contacts' => $contacts]);
            $this->store->write('vault', $vault);

            $listsMe = false;
            foreach ((array) ($b['cluster']['nodes'] ?? []) as $n) {
                if (is_array($n) && ($n['id'] ?? null) === $this->nodeId) {
                    $listsMe = true;
                }
            }
            if ($listsMe) {
                try {
                    Cluster::save($this->store, $b['cluster']);
                } catch (\InvalidArgumentException $e) {
                    $log[] = 'The primary sent a cluster list that was refused: ' . $e->getMessage();
                }
            } else {
                $log[] = 'The primary\'s cluster list does not include this node, so it was not used.';
            }
            $this->saveSync(['bundle_version' => $b['version']]);
            $log[] = 'Copied the settings, contacts and vault from the primary.';
        });
        return $log;
    }

    /**
     * The vault document from a bundle, passed through Vault's own checks by saving it into a
     * scratch store. Throws when any part is not valid, so a bad vault is never applied.
     */
    private static function cleanVault(array $in): array
    {
        $recipients = $in['recipients'] ?? [];
        $packages = $in['packages'] ?? [];
        $owner = $in['owner'] ?? null;
        if (!is_array($recipients) || !array_is_list($recipients) || !is_array($packages) || !array_is_list($packages)
            || ($owner !== null && !is_array($owner))) {
            throw new \RuntimeException('the bundle has no valid vault');
        }
        $dir = sys_get_temp_dir() . '/dps-vault-check-' . bin2hex(random_bytes(8));
        if (!@mkdir($dir, 0700)) {
            throw new \RuntimeException('cannot check the vault');
        }
        try {
            $scratch = new Store($dir);
            $vault = new Vault($scratch);
            if ($owner !== null) {
                $vault->initialise($owner, 'check');
            }
            foreach ($recipients as $r) {
                $vault->saveRecipient(is_array($r) ? $r : []);
            }
            foreach ($packages as $p) {
                $vault->savePackage(is_array($p) ? $p : []);
            }
            return $vault->read();
        } catch (\InvalidArgumentException $e) {
            throw new \RuntimeException('the bundle has no valid vault: ' . $e->getMessage());
        } finally {
            foreach (glob($dir . '/{,.}*', GLOB_BRACE) ?: [] as $f) {
                if (is_file($f)) {
                    @unlink($f);
                }
            }
            @rmdir($dir);
        }
    }

    /** @return string[] */
    private function pullBlobs(array $primary, int $deadline): array
    {
        $got = 0;
        $root = $this->store->dir() . '/vault';
        foreach ((array) ($this->store->read('vault')['packages'] ?? []) as $package) {
            foreach ((array) ($package['files'] ?? []) as $file) {
                $fid = $file['id'] ?? null;
                $chunks = $file['chunks'] ?? null;
                if (!is_string($fid) || !preg_match(self::ID, $fid) || !is_int($chunks) || $chunks < 1 || $chunks > 100000) {
                    continue;
                }
                for ($n = 0; $n < $chunks; $n++) {
                    $target = "$root/$fid/$n.bin";
                    if (is_file($target)) {
                        continue;
                    }
                    if (time() >= $deadline) {
                        return $got ? ["Copied $got vault pieces; the rest waits for the next run."] : [];
                    }
                    $dir = "$root/$fid";
                    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
                        throw new \RuntimeException('cannot create the vault folder');
                    }
                    try {
                        $this->client->download($primary, 'blob', ['file' => $fid, 'n' => (string) $n], $target);
                    } catch (\Throwable $e) {
                        return ["Could not copy a vault piece: " . $e->getMessage()];
                    }
                    if (filesize($target) > Vault::CHUNK_MAX) {
                        @unlink($target);
                        return ['A vault piece from the primary was too large and was dropped.'];
                    }
                    @chmod($target, 0600);
                    $got++;
                }
            }
        }
        return $got ? ["Copied $got vault pieces from the primary."] : [];
    }

    private function saveSync(array $changes): void
    {
        $this->store->locked(function () use ($changes): void {
            $this->store->write('sync', $changes + $this->store->read('sync'));
        });
    }

    /**
     * The primary's bundle for a secondary. The version is a checksum of the four documents,
     * so it changes whenever any of them does. Only the primary may build one.
     */
    public static function bundle(Store $store, int $since): array
    {
        $nodeId = Node::publicIdentity($store)['id'] ?? null;
        if ($nodeId === null || Cluster::read($store)['primary'] !== $nodeId) {
            throw new \LogicException('Only the primary serves the bundle.');
        }
        $docs = [
            'config' => $store->read('config'),
            'contacts' => $store->read('contacts'),
            'vault' => $store->read('vault'),
            'cluster' => Cluster::read($store),
        ];
        $version = max(1, crc32((string) json_encode($docs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)));
        if ($version === $since) {
            return ['unchanged' => true];
        }
        return ['version' => $version] + $docs;
    }
}

/** The engine for a node in a cluster: the normal engine plus a Coordinator for this node. */
function federatedEngine(): Engine
{
    static $engine = null;
    if ($engine === null) {
        $store = store();
        $id = Node::publicIdentity($store)['id'] ?? '';
        $engine = new Engine(
            $store,
            new Outbox($store, new SmtpTransport(), new HttpNotifier(appriseUrl())),
            null,
            new Coordinator($store, $id)
        );
    }
    return $engine;
}

/** The five handlers behind node-api.php. */
function federation(): array
{
    $store = store();
    $id = (string) (Node::publicIdentity($store)['id'] ?? '');
    return [
        'state' => static fn (): array => federatedEngine()->nodeState($id),
        'checkin' => static function (int $at, string $via) use ($store): void {
            $engine = federatedEngine();
            $state = $engine->state();
            // Only a check-in newer than ours, and not older than the running cycle, counts.
            if (!$state['armed'] || ($state['last_checkin_at'] !== null && $at <= $state['last_checkin_at'])) {
                return;
            }
            if ($state['cycle'] !== null && $at < (int) $state['cycle']['started_at']) {
                return;
            }
            $engine->checkInFromPeer($at, 'another node');
        },
        'nok' => static function (string $answer, int $at): void {
            federatedEngine()->recordNokAnswer($answer, $at, 'another node');
        },
        'bundle' => static fn (int $since): array => Sync::bundle($store, $since),
        'blob' => static fn (string $fileId, int $n): ?string => (new Vault($store))->chunkPath($fileId, $n),
    ];
}
