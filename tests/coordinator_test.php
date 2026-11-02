<?php
declare(strict_types=1);
namespace DPS\Tests;
use DPS\{Cluster, Coordinator, Store};
require __DIR__ . '/lib.php';

const SELF = 'n_aaaaaaaa';
const P1 = 'n_bbbbbbbb';
const P2 = 'n_cccccccc';

function nd(string $id, int $rank): array {
    return ['id' => $id, 'name' => 'N ' . $id, 'url' => 'https://a.example.org',
        'sign_pk' => rtrim(strtr(base64_encode(str_repeat('a', 32)), '+/', '-_'), '='),
        'share_pk' => 'MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAE', 'rank' => $rank];
}
function cw(array $ids, int $selfRank = 1): array {
    $dir = sys_get_temp_dir() . '/dps-coord-' . bin2hex(random_bytes(5));
    mkdir($dir, 0700);
    $store = new Store($dir);
    if ($ids) {
        $nodes = [nd(SELF, $selfRank)];
        $r = $selfRank === 0 ? 1 : 0;
        foreach ($ids as $id) { $nodes[] = nd($id, $r++); if ($r === $selfRank) { $r++; } }
        Cluster::save($store, ['k' => 1, 'primary' => null, 'isolation_hours' => 48, 'step_delay_min' => 30, 'nodes' => $nodes]);
    }
    return [$store, new Coordinator($store, SELF)];
}
function st(string $id, ?array $cycle = null, array $more = []): array {
    return $more + ['node' => $id, 'at' => 1000, 'last_checkin_at' => null, 'last_checkin_via' => null,
        'armed' => true, 'released' => false, 'cycle' => $cycle];
}

test('federation off lets the node act', function (): void {
    [, $c] = cw([]);
    same(true, $c->mayAct(5, 'fire', 1000, 1000), 'no cluster');
    same(false, $c->isolated(10_000_000), 'not isolated');
    same(0, $c->extraDelay('fire', 10_000_000), 'no delay');
});
test('mayAct waits for rank times the step delay', function (): void {
    [, $c] = cw([P1], 1);
    same(false, $c->mayAct(5, 'reminder1', 1000, 2799), 'just before');
    same(true, $c->mayAct(5, 'reminder1', 1000, 2800), 'at');
});
test('mayAct is false once a peer did the step', function (): void {
    [, $c] = cw([P1], 0);
    $c->recordPeer(P1, st(P1, ['key' => 5, 'stage' => 'checkin', 'steps' => ['reminder1' => 1500], 'fired_at' => null, 'fired_reason' => null, 'nok' => null]), 1600);
    same(false, $c->mayAct(5, 'reminder1', 1000, 99999), 'peer did');
    same(true, $c->mayAct(6, 'reminder1', 1000, 99999), 'other cycle');
    same(true, $c->mayAct(5, 'reminder2', 1000, 99999), 'other step');
});
test('a peer step dated more than the clock skew before the local due time is not believed', function (): void {
    [, $c] = cw([P1], 0);
    $c->recordPeer(P1, st(P1, ['key' => 5, 'stage' => 'verify', 'steps' => ['verify' => 1000], 'fired_at' => null, 'fired_reason' => null, 'nok' => null]), 1);
    same(null, $c->peerDid(5, 'verify', 1001), 'too early for the floor');
    same(1000, $c->peerDid(5, 'verify', 1000), 'at the floor');
    same(true, $c->mayAct(5, 'verify', 1601, 99999), 'early peer step ignored: this node acts');
    same(false, $c->mayAct(5, 'verify', 1600, 99999), 'within the skew: the peer did it');
});
test('peerDid takes the earliest across peers, even from a stale peer', function (): void {
    [, $c] = cw([P1, P2], 0);
    $cy = fn(int $at) => ['key' => 5, 'stage' => 'checkin', 'steps' => ['verify' => $at], 'fired_at' => null, 'fired_reason' => null, 'nok' => null];
    $c->recordPeer(P1, st(P1, $cy(900)), 1);
    $c->recordPeer(P2, st(P2, $cy(700)), 2);
    same(700, $c->peerDid(5, 'verify'), 'earliest');
    same(null, $c->peerDid(5, 'fire'), 'none');
    same(null, $c->peerDid(4, 'verify'), 'other cycle');
});
test('isolated: false with a peer seen 1 h ago, true at 7 h', function (): void {
    [, $c] = cw([P1], 1);
    same(true, $c->isolated(100_000), 'never seen');
    $c->recordPeer(P1, st(P1), 100_000 - 3600);
    same(false, $c->isolated(100_000), '1h');
    same(true, $c->isolated(100_000 - 3600 + 7 * 3600), '7h');
    same(false, $c->isolated(100_000 - 3600 + 21600), 'exactly 6h');
});
test('extraDelay only for verify and fire while isolated', function (): void {
    [, $c] = cw([P1], 1);
    same(172800, $c->extraDelay('fire', 100_000), 'fire');
    same(172800, $c->extraDelay('verify', 100_000), 'verify');
    same(0, $c->extraDelay('reminder1', 100_000), 'reminder1');
    same(0, $c->extraDelay('deliver:c_1', 100_000), 'deliver');
    $c->recordPeer(P1, st(P1), 99_000);
    same(0, $c->extraDelay('fire', 100_000), 'peer heard');
});
test('single-node cluster is never isolated', function (): void {
    [$s, $c] = cw([]);
    Cluster::save($s, ['k' => 1, 'primary' => null, 'isolation_hours' => 48, 'step_delay_min' => 30, 'nodes' => [nd(SELF, 0)]]);
    same(false, $c->isolated(100_000), 'single');
});
test('latestPeerCheckin picks the newest', function (): void {
    [, $c] = cw([P1, P2], 0);
    same(null, $c->latestPeerCheckin(), 'none');
    $c->recordPeer(P1, st(P1, null, ['last_checkin_at' => 500, 'last_checkin_via' => 'web']), 1);
    $c->recordPeer(P2, st(P2, null, ['last_checkin_at' => 800, 'last_checkin_via' => 'email']), 2);
    same(['at' => 800, 'via' => 'email', 'node' => P2], $c->latestPeerCheckin(), 'newest');
});
test('peerCycle merges peers and reports the most advanced stage', function (): void {
    [, $c] = cw([P1, P2], 0);
    same(null, $c->peerCycle(5), 'none');
    $c->recordPeer(P1, st(P1, ['key' => 5, 'stage' => 'verify', 'steps' => ['reminder1' => 300, 'verify' => 900],
        'fired_at' => null, 'fired_reason' => null, 'nok' => null]), 1);
    $c->recordPeer(P2, st(P2, ['key' => 5, 'stage' => 'fired', 'steps' => ['reminder1' => 200, 'fire' => 1200],
        'fired_at' => 1200, 'fired_reason' => 'no answer', 'nok' => ['answer' => 'ok', 'at' => 1300]]), 2);
    $c->recordPeer('n_dddddddd', st('n_dddddddd', ['key' => 4, 'stage' => 'fired', 'steps' => ['fire' => 1],
        'fired_at' => 1, 'fired_reason' => 'old', 'nok' => null]), 3);
    $pc = $c->peerCycle(5);
    same('fired', $pc['stage'], 'stage');
    same(1200, $pc['fired_at'], 'fired_at');
    same('no answer', $pc['fired_reason'], 'reason');
    same(['answer' => 'ok', 'at' => 1300], $pc['nok'], 'nok');
    same(['reminder1' => 200, 'verify' => 900, 'fire' => 1200], $pc['steps'], 'merged');
});
test('malformed peer states are ignored', function (): void {
    [$s, $c] = cw([P1], 0);
    $s->write('peers', [P1 => ['state' => 'junk', 'seen_at' => 5], 'x' => 7, P2 => ['state' => ['cycle' => ['key' => 5, 'steps' => 'no']]]]);
    same(null, $c->peerDid(5, 'fire'), 'did');
    same(null, $c->peerCycle(5), 'cycle');
    same(null, $c->latestPeerCheckin(), 'checkin');
    check($c->isolated(100_000) === true, 'isolated');
});
finish();
