<?php
declare(strict_types=1);
namespace DPS\Tests;
use DPS\{Cluster, Node, NodeApi, PeerClient, Store};
require __DIR__ . '/lib.php';

function pcStore(): Store
{
    $dir = sys_get_temp_dir() . '/dps-pc-' . bin2hex(random_bytes(5));
    mkdir($dir, 0700, true);
    return new Store($dir);
}

/**
 * Client store with an identity, a peer with its own identity, and a recording fake transport
 * that signs its answers as the peer would. $tamper may change [code, body, headers] after signing.
 */
function pcSetup(int $status = 200, string $reply = '{"ok":true}', ?callable $tamper = null): array
{
    $s = pcStore();
    $me = Node::init($s, 'me');
    $ps = pcStore();
    $pid = Node::init($ps, 'Big NAS');
    $peer = ['id' => $pid['id'], 'name' => 'Big NAS', 'url' => 'http://192.168.1.5:8080/dps/', 'sign_pk' => $pid['sign_pk']];
    $rec = new \stdClass();
    $rec->calls = [];
    $http = function (string $m, string $u, array $h, string $b) use ($rec, $status, $reply, $ps, $pid, $tamper): array {
        $rec->calls[] = [$m, $u, $h, $b];
        $nonce = (string)hdr($h, 'X-DPS-Nonce');
        $r = [$status, $reply, ['x-dps-node' => $pid['id'],
            'x-dps-signature' => Node::sign($ps, NodeApi::responseCanonical($nonce, $status, hash('sha256', $reply)))]];
        return $tamper === null ? $r : $tamper($r, $nonce, $ps);
    };
    return [$s, $me, $peer, $rec, new PeerClient($s, $http)];
}

function hdr(array $headers, string $name): ?string
{
    foreach ($headers as $line) {
        if (stripos($line, $name . ':') === 0) {
            return trim(substr($line, strlen($name) + 1));
        }
    }
    return null;
}

test('get builds the URL, sends four headers and a verifying signature', function (): void {
    [$s, $me, $peer, $rec, $c] = pcSetup();
    same(['ok' => true], $c->get($peer, 'bundle', ['since' => 7]), 'decoded');
    [$m, $url, $h, $b] = $rec->calls[0];
    same('GET', $m, 'method');
    same('http://192.168.1.5:8080/dps/node-api.php?a=bundle&since=7', $url, 'url');
    same($me['id'], hdr($h, 'X-DPS-Node'), 'node');
    $t = (int)hdr($h, 'X-DPS-Time');
    check(abs($t - time()) < 5, 'time');
    check((bool)preg_match('/^[0-9a-f]{16}$/', (string)hdr($h, 'X-DPS-Nonce')), 'nonce');
    $canon = Node::canonical('GET', '/dps/node-api.php?a=bundle&since=7', '', $t, (string)hdr($h, 'X-DPS-Nonce'));
    check(Node::verify($me['sign_pk'], $canon, (string)hdr($h, 'X-DPS-Signature')), 'signature verifies');
});
test('post sends a JSON body and Content-Type', function (): void {
    [$s, $me, $peer, $rec, $c] = pcSetup();
    $c->post($peer, 'checkin', ['at' => 5, 'via' => 'web']);
    [$m, , $h, $b] = $rec->calls[0];
    same('POST', $m, 'method');
    same(['at' => 5, 'via' => 'web'], json_decode($b, true), 'body');
    same('application/json', hdr($h, 'Content-Type'), 'type');
    $canon = Node::canonical('POST', '/dps/node-api.php?a=checkin', $b, (int)hdr($h, 'X-DPS-Time'), (string)hdr($h, 'X-DPS-Nonce'));
    check(Node::verify($me['sign_pk'], $canon, (string)hdr($h, 'X-DPS-Signature')), 'signature covers the body');
});
test('a 500 and invalid JSON throw with the peer name', function (): void {
    [, , $peer, , $c] = pcSetup(500, 'oops');
    $msg = '';
    try { $c->get($peer, 'state'); } catch (\RuntimeException $e) { $msg = $e->getMessage(); }
    same('Big NAS: HTTP 500', $msg, 'http error');
    [, , $peer, , $c] = pcSetup(200, '<html>');
    $threw = false;
    try { $c->get($peer, 'state'); } catch (\RuntimeException $e) { $threw = str_contains($e->getMessage(), 'Big NAS'); }
    check($threw, 'bad json');
});
test('download writes the bytes and refuses a failed answer', function (): void {
    [, , $peer, $rec, $c] = pcSetup(200, "bytes\0here");
    $to = sys_get_temp_dir() . '/dps-pc-dl-' . bin2hex(random_bytes(4));
    $c->download($peer, 'blob', ['file' => 'f1', 'n' => 0], $to);
    same("bytes\0here", file_get_contents($to), 'content');
    same('http://192.168.1.5:8080/dps/node-api.php?a=blob&file=f1&n=0', $rec->calls[0][1], 'url');
    @unlink($to);
    [, , $peer, , $c] = pcSetup(404, 'x');
    $to2 = $to . '2';
    $threw = false;
    try { $c->download($peer, 'blob', ['file' => 'f1', 'n' => 0], $to2); } catch (\RuntimeException) { $threw = true; }
    check($threw && !file_exists($to2), 'failure leaves no file');
});
test('seam: a request built by PeerClient is accepted by NodeApi, once', function (): void {
    $a = pcStore();
    $b = pcStore();
    $ia = Node::init($a, 'a');
    $ib = Node::init($b, 'b');
    $cluster = ['k' => 1, 'primary' => $ib['id'], 'nodes' => [$ia + ['url' => 'http://192.168.1.2', 'rank' => 0], $ib + ['url' => 'http://192.168.1.3/sub', 'rank' => 1]]];
    Cluster::save($a, $cluster);
    Cluster::save($b, $cluster);
    $api = new NodeApi($b, [
        'state' => fn (): array => ['node' => 'b'],
        'checkin' => function (int $at, string $via): void {},
        'nok' => function (string $answer, int $at): void {},
        'bundle' => fn (int $since): array => ['unchanged' => true],
        'blob' => fn (string $f, int $n): ?string => null,
    ]);
    $last = null;
    $http = function (string $m, string $url, array $headers, string $body) use ($api, &$last): array {
        $p = parse_url($url);
        parse_str($p['query'], $q);
        $last = [
            'method' => $m, 'a' => $q['a'], 'pathQuery' => $p['path'] . '?' . $p['query'], 'query' => $q, 'body' => $body, 'now' => time(),
            'headers' => ['node' => hdr($headers, 'X-DPS-Node'), 'time' => hdr($headers, 'X-DPS-Time'),
                'nonce' => hdr($headers, 'X-DPS-Nonce'), 'signature' => hdr($headers, 'X-DPS-Signature')],
        ];
        $r = $api->handle($last);
        return [$r['status'], $r['body'] ?? json_encode($r['json'] ?? []), array_change_key_case($r['headers'] ?? [])];
    };
    $c = new PeerClient($a, $http);
    $peer = $ib + ['url' => 'http://192.168.1.3/sub'];
    same(['node' => 'b'], $c->get($peer, 'state'), 'get accepted');
    same(['unchanged' => true], $c->get($peer, 'bundle', ['since' => 3]), 'query accepted');
    $c->post($peer, 'checkin', ['at' => time() - 1, 'via' => 'web']);
    same(409, $api->handle($last)['status'], 'replaying the same request is refused');
});
function badSig(callable $tamper, string $how = 'get'): string
{
    [, , $peer, , $c] = pcSetup(200, '{"ok":true}', $tamper);
    try {
        $how === 'get' ? $c->get($peer, 'state') : $c->post($peer, 'checkin', ['at' => 1, 'via' => 'x']);
    } catch (\RuntimeException $e) {
        return $e->getMessage();
    }
    return 'accepted';
}
test('a tampered answer body is refused before it is used', function (): void {
    same('Big NAS: bad response signature', badSig(fn (array $r): array => [$r[0], '{"ok":false}', $r[2]]), 'get');
    same('Big NAS: bad response signature', badSig(fn (array $r): array => [$r[0], '{"ok":false}', $r[2]], 'post'), 'post');
    same('Big NAS: bad response signature', badSig(fn (array $r): array => [$r[0], $r[1], []]), 'unsigned');
    same('accepted', badSig(fn (array $r): array => $r), 'the untouched answer is accepted');
});
test('an answer signed by another node, or naming another node, is refused', function (): void {
    $other = pcStore();
    $io = Node::init($other, 'other');
    same('Big NAS: bad response signature', badSig(function (array $r, string $nonce) use ($other, $io): array {
        return [$r[0], $r[1], ['x-dps-node' => $io['id'], 'x-dps-signature' => Node::sign($other, NodeApi::responseCanonical($nonce, 200, hash('sha256', $r[1])))]];
    }), 'other signer and id');
    same('Big NAS: bad response signature', badSig(fn (array $r): array => [$r[0], $r[1], ['x-dps-node' => $io['id']] + $r[2]]), 'wrong id, right key');
});
test('a replayed answer (signed over another nonce) is refused', function (): void {
    same('Big NAS: bad response signature', badSig(function (array $r, string $nonce, Store $ps): array {
        $r[2]['x-dps-signature'] = Node::sign($ps, NodeApi::responseCanonical('00112233445566ff', 200, hash('sha256', $r[1])));
        return $r;
    }), 'old nonce');
    same('Big NAS: bad response signature', badSig(function (array $r, string $nonce, Store $ps): array {
        $r[2]['x-dps-signature'] = Node::sign($ps, NodeApi::responseCanonical($nonce, 201, hash('sha256', $r[1])));
        return $r;
    }), 'other status');
});
test('a download with a bad signature leaves no file behind', function (): void {
    [, , $peer, , $c] = pcSetup(200, 'good bytes', fn (array $r): array => [$r[0], 'evil bytes', $r[2]]);
    $to = sys_get_temp_dir() . '/dps-pc-dl-' . bin2hex(random_bytes(4));
    $msg = '';
    try { $c->download($peer, 'blob', ['file' => 'f1', 'n' => 0], $to); } catch (\RuntimeException $e) { $msg = $e->getMessage(); }
    same('Big NAS: bad response signature', $msg, 'refused');
    check(!file_exists($to) && !file_exists($to . '.part'), 'nothing written');
});
finish();
