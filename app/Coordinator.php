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

    /** The earliest time any peer did $step in cycle $cycleKey. */
    public function peerDid(int $cycleKey, string $step): ?int
    {
        $best = null;
        foreach ($this->peerCycles($cycleKey) as $cy) {
            $t = $cy['steps'][$step] ?? null;
            if (is_int($t) && ($best === null || $t < $best)) {
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
        if ($this->peerDid($cycleKey, $step) !== null) {
            return false;
        }
        $c = Cluster::read($this->store);
        $rank = Cluster::rank($this->store, $this->nodeId);
        return $now >= $dueAt + $rank * $c['step_delay_min'] * 60;
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
                    'fired_at' => null, 'fired_reason' => null, 'nok' => ['answer' => 'fine', 'at' => $v['at']]];
            }
        }
        return $out;
    }
}
