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
        'trust',
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
        // Each answer carries the key of the cycle it was given in, so a peer never applies it to a later cycle.
        $pushedNok = (int) ($sync['pushed_nok_at'] ?? 0);
        $answers = [];
        $own = $this->engine->nodeState($this->nodeId)['cycle'] ?? null;
        $nok = $own['nok'] ?? null;
        if (is_array($nok) && $nok['at'] > $pushedNok) {
            $answers[] = [(string) $nok['answer'], (int) $nok['at'], ['key' => (int) $own['key']]];
        }
        foreach ($this->store->history(50) as $row) {
            // "All fine" ends the cycle, so only the history remembers it. Answers taken from a peer carry "(via)".
            if (($row['event'] ?? '') === 'cycle.vouched' && (int) $row['at'] > $pushedNok
                && !str_contains((string) ($row['text'] ?? ''), '" (')) {
                $extra = [];
                if (is_int($row['key'] ?? null)) {
                    $extra['key'] = $row['key'];
                }
                if (is_string($row['by'] ?? null)) {
                    $extra['by'] = $row['by'];
                }
                $answers[] = ['fine', (int) $row['at'], $extra];
            }
        }
        if ($answers) {
            $ok = true;
            foreach ($answers as [$answer, $when, $extra]) {
                foreach ($peers as $peer) {
                    try {
                        $this->client->post($peer, 'nok', ['answer' => $answer, 'at' => $when] + $extra);
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
            // The chain of trusted people: a flag and an order number, or neither (older data). "Not told in advance" is a flag too.
            if (!is_array($c)
                || (array_key_exists('trusted', $c) && !is_bool($c['trusted']))
                || (array_key_exists('silent', $c) && !is_bool($c['silent']))
                || (($c['trusted_order'] ?? null) !== null && (!is_int($c['trusted_order']) || $c['trusted_order'] < 1 || $c['trusted_order'] > 100000))) {
                throw new \RuntimeException('the bundle has no valid contacts');
            }
        }
        if (array_key_exists('trust', $config) && !self::validTrust($config['trust'])) {
            throw new \RuntimeException('the bundle has no valid trust settings');
        }
        // Group channels: a primary from before they existed sends none, and then the local list is left as it is.
        $groups = null;
        $log = [];
        if (array_key_exists('groups', $b)) {
            $groups = self::cleanGroups($b['groups'], $log);
            if ($groups === null) {
                throw new \RuntimeException('the bundle has no valid group channels');
            }
        }
        $vault = self::cleanVault($b['vault']);
        // "Not told in advance" never goes with being asked or holding vault packages: such a contact keeps being told.
        $vaultIds = array_filter(array_column((array) ($vault['recipients'] ?? []), 'contact_id'), 'is_string');
        foreach ($contacts as $i => $c) {
            if (($c['silent'] ?? false) === true
                && (($c['kind'] ?? '') === 'nok' || ($c['trusted'] ?? false) === true || ($c['trusted_order'] ?? null) !== null
                    || in_array($c['id'] ?? null, $vaultIds, true))) {
                $contacts[$i]['silent'] = false;
                $log[] = 'The primary sent contact ' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($c['id'] ?? '')) . ' as not told in advance, but they are asked or hold vault packages, so the flag was dropped.';
            }
        }
        $this->store->locked(function () use ($b, $config, $contacts, $groups, $vault, &$log): void {
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
            if ($groups !== null) {
                $this->store->write('groups', ['groups' => $groups]);
            }
            $this->store->write('vault', $vault);
            if (isset($vault['shares'])) {
                // A secondary never holds the full release key.
                $rel = $this->store->path('release');
                @unlink($rel);
                @unlink($rel . '.bak');
            }

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
     * The group channels of a bundle, or null when the document itself is not a list of them. Each address
     * passes the same checks as one the owner types in, so a bundle cannot bring in an address the form would
     * refuse. A group that is not valid, repeats an id, or is past the limit of Groups::MAX is skipped, and
     * $log says so; the others are kept.
     *
     * @param string[] $log
     * @return list<array>|null
     */
    private static function cleanGroups(mixed $doc, array &$log): ?array
    {
        if ($doc === []) {
            return [];
        }
        $list = is_array($doc) ? ($doc['groups'] ?? null) : null;
        if (!is_array($list) || !array_is_list($list)) {
            return null;
        }
        $out = [];
        $seen = [];
        foreach ($list as $i => $g) {
            $why = self::groupProblem($g, $seen);
            if ($why === null && count($out) >= Groups::MAX) {
                $why = 'more than ' . Groups::MAX . ' group channels';
            }
            if ($why !== null) {
                $log[] = 'Skipped group channel ' . ($i + 1) . ' from the primary: ' . $why . '.';
                continue;
            }
            $ch = $g['channel'];
            $name = Groups::cleanName($g['name']);
            $seen[$g['id']] = true;
            $out[] = [
                'id' => $g['id'],
                'name' => $name,
                'channel' => [
                    'id' => is_string($ch['id'] ?? null) && preg_match('/^ch_[0-9a-f]{10}$/', $ch['id']) === 1 ? $ch['id'] : (string) Channels::normalize(['type' => $ch['type'], 'url' => $ch['url']])['id'],
                    'type' => $ch['type'],
                    'url' => $ch['url'],
                    'label' => mb_substr($name, 0, 60),
                    'secret' => $ch['secret'] ?? null,
                    'created_at' => (int) ($ch['created_at'] ?? 0),
                ],
                'text' => $g['text'],
                'correction' => ($g['correction'] ?? true) !== false,
                'correction_text' => is_string($g['correction_text'] ?? null) && mb_strlen($g['correction_text']) <= Groups::TEXT_MAX ? $g['correction_text'] : '',
                'created_at' => (int) ($g['created_at'] ?? 0),
                'updated_at' => (int) ($g['updated_at'] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * What is wrong with one group channel of a bundle, in a few words, or null if nothing is.
     *
     * @param array<string, true> $seen the ids already taken
     */
    private static function groupProblem(mixed $g, array $seen): ?string
    {
        if (!is_array($g) || !is_string($g['id'] ?? null) || preg_match('/^g_[0-9a-f]{10}$/D', $g['id']) !== 1) {
            return 'no valid id';
        }
        if (isset($seen[$g['id']])) {
            return 'the same id twice';
        }
        $name = is_string($g['name'] ?? null) ? Groups::cleanName($g['name']) : '';
        if ($name === '' || mb_strlen($name) > Groups::NAME_MAX) {
            return 'no valid name';
        }
        if (!is_string($g['text'] ?? null) || trim($g['text']) === '' || mb_strlen($g['text']) > Groups::TEXT_MAX) {
            return 'no valid text';
        }
        $correction = is_string($g['correction_text'] ?? null) ? $g['correction_text'] : '';
        if (Groups::refusedPlaceholders($g['text'] . ' ' . $correction) !== []) {
            return 'a placeholder with the contacts\' details';
        }
        $ch = $g['channel'] ?? null;
        if (!is_array($ch) || !is_string($ch['url'] ?? null) || !is_string($ch['type'] ?? null)) {
            return 'no valid address';
        }
        $check = Channels::normalize(['type' => $ch['type'], 'url' => $ch['url'], 'label' => $name]);
        if ($check === null || $check['url'] !== $ch['url']) {
            return 'an address the form would refuse';
        }
        $secret = $ch['secret'] ?? null;
        if ($ch['type'] === 'webhook' ? (!is_string($secret) || preg_match('/^[0-9a-f]{32}$/D', $secret) !== 1) : $secret !== null) {
            return 'no valid signing secret';
        }
        return null;
    }

    /** The chain settings of a bundle: a known mode, an interval of "auto" or 15 to 1440 minutes, a window of 6 to 72 hours. */
    private static function validTrust(mixed $t): bool
    {
        if (!is_array($t)) {
            return false;
        }
        $interval = $t['interval'] ?? 'auto';
        return in_array($t['mode'] ?? null, Trust::MODES, true)
            && ($interval === 'auto' || (is_int($interval) && $interval >= Trust::INTERVAL_MIN && $interval <= Trust::INTERVAL_MAX))
            && is_int($t['window'] ?? null) && $t['window'] >= Trust::WINDOW_MIN && $t['window'] <= Trust::WINDOW_MAX;
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
            $shares = $in['shares'] ?? null;
            if ($shares !== null) {
                if (!is_array($shares)) {
                    throw new \RuntimeException('the bundle has no valid vault: shares');
                }
                $vault->saveShares($shares);
            }
            $doc = $vault->read();
            if ($shares !== null && is_int($shares['version'] ?? null) && $shares['version'] > 0) {
                $doc['shares']['version'] = $shares['version'];
            }
            return $doc;
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
     * The primary's bundle for a secondary. The version is a checksum of the five documents,
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
            'groups' => $store->read('groups'),
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
        'nok' => static function (string $answer, int $at, ?int $key = null, ?string $by = null): void {
            federatedEngine()->recordNokAnswer($answer, $at, 'another node', $key, $by);
        },
        'bundle' => static fn (int $since): array => Sync::bundle($store, $since),
        'blob' => static fn (string $fileId, int $n): ?string => (new Vault($store))->chunkPath($fileId, $n),
    ];
}
