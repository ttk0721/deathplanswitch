<?php
/**
 * Death Plan Switch: this node's identity and cluster membership.
 *
 *   php cli/node.php init --name=<name>
 *   php cli/node.php show
 *   php cli/node.php join --primary-url=<url> --primary='<identity json>' [--url=<this node's url>]
 *
 * "join" makes a two-node cluster: the primary at rank 0, this node at rank 1.
 * The primary then adds this node on its Nodes page.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use DPS\Cluster;
use DPS\Node;

DPS\Cli::requireDataOwner();

function fail(string $message): never
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

$command = $argv[1] ?? '';
$opts = [];
foreach (array_slice($argv, 2) as $arg) {
    if (preg_match('/^--([a-z-]+)=(.*)$/s', $arg, $m)) {
        $opts[$m[1]] = $m[2];
    }
}
$store = DPS\store();

switch ($command) {
    case 'init':
        try {
            $identity = Node::init($store, (string) ($opts['name'] ?? ''));
        } catch (\Throwable $e) {
            fail($e->getMessage());
        }
        echo json_encode($identity, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
        break;

    case 'show':
        $identity = Node::publicIdentity($store);
        if ($identity === null) {
            fail('This node has no identity yet. Run: php cli/node.php init --name=<name>');
        }
        echo json_encode($identity, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
        break;

    case 'join':
        $self = Node::publicIdentity($store);
        if ($self === null) {
            fail('This node has no identity yet. Run: php cli/node.php init --name=<name>');
        }
        $primary = json_decode((string) ($opts['primary'] ?? ''), true);
        if (!is_array($primary)) {
            fail('--primary must be the primary node\'s identity JSON.');
        }
        $primaryUrl = rtrim((string) ($opts['primary-url'] ?? ''), '/');
        $selfUrl = rtrim((string) ($opts['url'] ?? ($store->read('config')['base_url'] ?? '')), '/');
        if ($selfUrl === '') {
            fail('This node\'s own address is not known. Pass --url=<this node\'s address>.');
        }
        if (Cluster::enabled($store)) {
            fail('This node is already in a cluster.');
        }
        $entry = static fn (array $id, string $url, int $rank): array => [
            'id' => $id['id'] ?? null, 'name' => $id['name'] ?? null, 'url' => $url,
            'sign_pk' => $id['sign_pk'] ?? null, 'share_pk' => $id['share_pk'] ?? null, 'rank' => $rank,
        ];
        $cluster = Cluster::read($store);
        $cluster['nodes'] = [$entry($primary, $primaryUrl, 0), $entry($self, $selfUrl, 1)];
        $cluster['primary'] = $primary['id'] ?? null;
        $cluster['k'] = 1;
        try {
            Cluster::save($store, $cluster);
        } catch (\InvalidArgumentException $e) {
            fail($e->getMessage());
        }
        echo 'Joined. This node mirrors ' . ($primary['name'] ?? '') . ". Now add this node on the primary's Nodes page.\n";
        break;

    default:
        fail("Usage:\n  php cli/node.php init --name=<name>\n  php cli/node.php show\n"
            . "  php cli/node.php join --primary-url=<url> --primary='<identity json>' [--url=<this node's url>]");
}
