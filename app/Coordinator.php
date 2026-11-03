<?php
declare(strict_types=1);

namespace DPS;

/**
 * Keeps the last known state of each peer (data/peers.json) and answers
 * whether this node may act on a step, and whether a peer already did.
 */
final class Coordinator
{
    private const DOC = 'peers';
    private const ISOLATION_WINDOW = 21600;
    private const STAGE_RANK = ['checkin' => 1, 'verify' => 2, 'fired' => 3];
    /** Seconds two nodes' clocks may differ. A peer step dated earlier than this before its local due time is not believed. */
    public const CLOCK_SKEW = 600;

    public function __construct(private Store $store, private string $nodeId)
    {
    }

    public function recordPeer(string $peerId, array $state, int $now): void
    {
        $this->store->locked(function () use ($peerId, $state, $now): void {
            $all = $this->store->read(self::DOC);
            $all[$peerId] = ['state' => $state, 'seen_at' => $now];
            $this->store->write(self::DOC, $all);
        });
    }

    /**
     * The earliest time any peer did $step in cycle $cycleKey, not counting times before $notBefore.
     * A peer dating a step before this node's own due time must not pull this node's timeline forward.
     */
    public function peerDid(int $cycleKey, string $step, ?int $notBefore = null): ?int
    {
        $best = null;
        foreach ($this->peerCycles($cycleKey) as $cy) {
            $t = $cy['steps'][$step] ?? null;
            if (is_int($t) && ($notBefore === null || $t >= $notBefore) && ($best === null || $t < $best)) {
                $best = $t;
            }
        }
        return $best;
    }

    public function mayAct(int $cycleKey, string $step, int $dueAt, int $now): bool
    {
        if (!Cluster::enabled($this->store)) {
            return true;
        }
        // An early-dated peer step is ignored: this node then acts on its own timeline.
        if ($this->peerDid($cycleKey, $step, $dueAt - self::CLOCK_SKEW) !== null) {
            return false;
        }
        $c = Cluster::read($this->store);
        $rank = Cluster::rank($this->store, $this->nodeId);
        return $now >= $dueAt + $rank * $c['step_delay_min'] * 60;
    }

    public function nodeId(): string
    {
        return $this->nodeId;
    }

    /** Seconds this node waits after a lower-ranked node before it acts on a step: 0 for the first node. */
    public function rankDelay(): int
    {
        if (!Cluster::enabled($this->store)) {
            return 0;
        }
        return Cluster::rank($this->store, $this->nodeId) * Cluster::read($this->store)['step_delay_min'] * 60;
    }

    /**
     * What peers report of one group channel's delivery in cycle $cycleKey: the frozen text a peer sends,
     * and the latest lease on it. A record whose hash does not match its text is not believed. When peers
     * froze different texts, the one with a lease wins, then the one frozen first.
     *
     * @return array{hash: string, text: string, limit: int, utf16: bool, frozen_at: int, lease: ?string, lease_at: ?int}|null
     */
    public function peerGroup(int $cycleKey, string $id): ?array
    {
        $best = null;
        $records = [];
        foreach ($this->peerCycles($cycleKey) as $cy) {
            $g = $cy['groups'][$id] ?? null;
            if (!is_array($g) || !is_string($g['hash'] ?? null) || !is_string($g['text'] ?? null)
                || !hash_equals((string) $g['hash'], hash('sha256', $g['text']))
                || !is_int($g['limit'] ?? null) || $g['limit'] < 100 || $g['limit'] > 40000
                || !is_int($g['frozen_at'] ?? null) || mb_strlen($g['text']) > 2 * Groups::TEXT_MAX) {
                continue;
            }
            $lease = is_string($g['lease'] ?? null) && preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $g['lease']) === 1 && is_int($g['lease_at'] ?? null)
                ? [$g['lease'], $g['lease_at']] : [null, null];
            $records[] = ['hash' => $g['hash'], 'text' => $g['text'], 'limit' => $g['limit'], 'utf16' => ($g['utf16'] ?? false) === true,
                'frozen_at' => $g['frozen_at'], 'lease' => $lease[0], 'lease_at' => $lease[1]];
        }
        // Better: has a lease, then frozen earlier, then the smaller hash, so every node picks the same one.
        $rank = static fn (array $r): array => [$r['lease'] === null ? 1 : 0, $r['frozen_at'], $r['hash']];
        foreach ($records as $r) {
            if ($best === null || $rank($r) < $rank($best)) {
                $best = $r;
            }
        }
        if ($best === null) {
            return null;
        }
        // The latest lease on that text: a node that took the delivery over holds it now.
        foreach ($records as $r) {
            if ($r['hash'] === $best['hash'] && $r['lease'] !== null
                && ($best['lease'] === null || [$r['lease_at'], $r['lease']] > [$best['lease_at'], $best['lease']])) {
                $best['lease'] = $r['lease'];
                $best['lease_at'] = $r['lease_at'];
            }
        }
        return $best;
    }

    /** The earliest time any peer reports having sent the correction $step, or null. */
    public function peerCorrection(string $step): ?int
    {
        $best = null;
        foreach ($this->peers() as $p) {
            $t = $p['state']['corrections'][$step] ?? null;
            if (is_int($t) && ($best === null || $t < $best)) {
                $best = $t;
            }
        }
        return $best;
    }

    /** Federation is on and no peer was heard from in the last 6 hours. */
    public function isolated(int $now): bool
    {
        if (!Cluster::enabled($this->store)) {
            return false;
        }
        foreach ($this->peers() as $p) {
            if ($p['seen_at'] >= $now - self::ISOLATION_WINDOW) {
                return false;
            }
        }
        return true;
    }

    /** Extra seconds to wait before a serious step while cut off from every peer. */
    public function extraDelay(string $step, int $now): int
    {
        if ($step !== 'verify' && $step !== 'fire') {
            return 0;
        }
        return $this->isolated($now) ? Cluster::read($this->store)['isolation_hours'] * 3600 : 0;
    }

    /** @return array{at: int, via: string, node: string}|null */
    public function latestPeerCheckin(): ?array
    {
        $best = null;
        foreach ($this->peers() as $id => $p) {
            $s = $p['state'];
            $at = $s['last_checkin_at'] ?? null;
            $via = $s['last_checkin_via'] ?? null;
            if (is_int($at) && is_string($via) && ($best === null || $at > $best['at'])) {
                $best = ['at' => $at, 'via' => $via, 'node' => $id];
            }
        }
        return $best;
    }

    /** The most advanced peer view of cycle $cycleKey, merged across peers. */
    public function peerCycle(int $cycleKey): ?array
    {
        $cycles = $this->peerCycles($cycleKey);
        if (!$cycles) {
            return null;
        }
        $stage = null;
        $rank = 0;
        $steps = [];
        $firedAt = null;
        $firedReason = null;
        $nok = null;
        foreach ($cycles as $cy) {
            $r = self::STAGE_RANK[$cy['stage'] ?? ''] ?? 0;
            if ($stage === null || $r > $rank) {
                $stage = is_string($cy['stage'] ?? null) ? $cy['stage'] : null;
                $rank = $r;
            }
            foreach ($cy['steps'] as $name => $t) {
                if (is_int($t) && (!isset($steps[$name]) || $t < $steps[$name])) {
                    $steps[$name] = $t;
                }
            }
            $fa = $cy['fired_at'] ?? null;
            if (is_int($fa) && ($firedAt === null || $fa < $firedAt)) {
                $firedAt = $fa;
                $firedReason = is_string($cy['fired_reason'] ?? null) ? $cy['fired_reason'] : null;
            }
            $n = $cy['nok'] ?? null;
            if (is_array($n) && is_int($n['at'] ?? null) && ($nok === null || $n['at'] < $nok['at'])) {
                $nok = $n;
            }
        }
        return ['stage' => $stage, 'fired_at' => $firedAt, 'fired_reason' => $firedReason, 'nok' => $nok, 'steps' => $steps];
    }

    /**
     * Every "all fine" any peer reports for cycle $cycleKey, oldest first, with the id of the person who gave it when known.
     *
     * @return list<array{at: int, by: ?string}>
     */
    public function peerFines(int $cycleKey): array
    {
        $out = [];
        foreach ($this->peerCycles($cycleKey) as $cy) {
            $n = $cy['nok'] ?? null;
            if (is_array($n) && ($n['answer'] ?? null) === 'fine' && is_int($n['at'] ?? null)) {
                $by = $n['by'] ?? null;
                $out[] = ['at' => $n['at'], 'by' => is_string($by) && preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $by) ? $by : null];
            }
        }
        usort($out, static fn (array $a, array $b): int => $a['at'] <=> $b['at']);
        return $out;
    }

    /** @return list<array> the chain and timing each peer runs cycle $cycleKey on, as they report it (unchecked) */
    public function peerTrusts(int $cycleKey): array
    {
        $out = [];
        foreach ($this->peerCycles($cycleKey) as $cy) {
            if (is_array($cy['trust'] ?? null)) {
                $out[] = $cy['trust'];
            }
        }
        return $out;
    }

    /** @return array<string, array{state: array, seen_at: int}> well-formed peers, self excluded */
    private function peers(): array
    {
        $out = [];
        foreach ($this->store->read(self::DOC) as $id => $p) {
            if ($id === $this->nodeId || !is_array($p) || !is_array($p['state'] ?? null) || !is_int($p['seen_at'] ?? null)) {
                continue;
            }
            $out[(string)$id] = ['state' => $p['state'], 'seen_at' => $p['seen_at']];
        }
        return $out;
    }

    /** @return list<array> the cycle blocks of peers that report cycle $cycleKey, with a valid steps map */
    private function peerCycles(int $cycleKey): array
    {
        $out = [];
        foreach ($this->store->read(self::DOC) as $id => $p) {
            if ($id === $this->nodeId || !is_array($p) || !is_array($p['state'] ?? null)) {
                continue;
            }
            $cy = $p['state']['cycle'] ?? null;
            if (is_array($cy) && ($cy['key'] ?? null) === $cycleKey && is_array($cy['steps'] ?? null)) {
                $out[] = $cy;
            }
            // A peer that closed this cycle on the kin's "all fine" reports it apart from its running cycle.
            $v = $p['state']['vouched'] ?? null;
            if (is_array($v) && ($v['key'] ?? null) === $cycleKey && is_int($v['at'] ?? null) && is_array($v['steps'] ?? null)) {
                $out[] = ['key' => $cycleKey, 'stage' => 'vouched', 'steps' => $v['steps'],
                    'fired_at' => null, 'fired_reason' => null, 'nok' => ['answer' => 'fine', 'at' => $v['at']] + (isset($v['by']) ? ['by' => $v['by']] : [])];
            }
        }
        return $out;
    }
}
