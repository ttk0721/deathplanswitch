<?php
declare(strict_types=1);

namespace DPS;

if (!defined('DPS_ROOT')) {
    exit; // only ever loaded by Web::page()
}

Web::requireOwner();
$store = store();
$config = $store->read('config');
$tz = (string) ($config['timezone'] ?? 'UTC');
$identity = Node::publicIdentity($store);
$isPrimary = $identity === null || Cluster::isPrimary($store, $identity['id']);

if (Web::posted()) {
    if ($identity === null || !$isPrimary) {
        Web::flash('Cluster settings are changed on the primary node.', 'bad');
        Web::redirect('index.php?p=nodes');
    }
    try {
        switch (Web::post('action')) {
            case 'node_add':
                $peer = json_decode(Web::post('identity'), true);
                if (!is_array($peer)) {
                    throw new \InvalidArgumentException('Paste the identity JSON shown on the other node.');
                }
                $url = rtrim(Web::post('url'), '/');
                $rank = ctype_digit(Web::post('rank')) ? (int) Web::post('rank') : -1;
                $store->locked(static function () use ($store, $identity, $peer, $url, $rank, $config): void {
                    $cluster = Cluster::read($store);
                    if ($cluster['nodes'] === []) {
                        // The first peer: this node joins the list as rank 0 and primary.
                        $cluster['nodes'][] = [
                            'id' => $identity['id'], 'name' => $identity['name'], 'url' => rtrim((string) ($config['base_url'] ?? ''), '/'),
                            'sign_pk' => $identity['sign_pk'], 'share_pk' => $identity['share_pk'], 'rank' => 0,
                        ];
                        $cluster['primary'] = $identity['id'];
                    }
                    $cluster['nodes'][] = [
                        'id' => $peer['id'] ?? null, 'name' => $peer['name'] ?? null, 'url' => $url,
                        'sign_pk' => $peer['sign_pk'] ?? null, 'share_pk' => $peer['share_pk'] ?? null, 'rank' => $rank,
                    ];
                    Cluster::save($store, $cluster);
                    $store->log('cluster.add', 'A node was added to the cluster.');
                });
                Web::flash('Node added.');
                break;

            case 'node_remove':
                $id = Web::post('node_id');
                if ($id === $identity['id']) {
                    throw new \InvalidArgumentException('This node cannot remove itself.');
                }
                $store->locked(static function () use ($store, $id): void {
                    $cluster = Cluster::read($store);
                    $cluster['nodes'] = array_values(array_filter($cluster['nodes'], static fn (array $n): bool => $n['id'] !== $id));
                    $cluster['k'] = max(1, min($cluster['k'], count($cluster['nodes'])));
                    Cluster::save($store, $cluster);
                    $store->log('cluster.remove', 'A node was removed from the cluster.');
                });
                Web::flash('Node removed.', 'warn');
                break;

            case 'cluster_save':
                $store->locked(static function () use ($store): void {
                    $cluster = Cluster::read($store);
                    foreach (['k' => 'k', 'isolation_hours' => 'isolation_hours', 'step_delay_min' => 'step_delay_min'] as $field => $key) {
                        $v = Web::post($field);
                        if (!ctype_digit($v)) {
                            throw new \InvalidArgumentException('Enter whole numbers.');
                        }
                        $cluster[$key] = (int) $v;
                    }
                    Cluster::save($store, $cluster);
                    $store->log('cluster.settings', 'The cluster settings were changed.');
                });
                Web::flash('Saved.');
                break;
        }
    } catch (\InvalidArgumentException $e) {
        Web::flash($e->getMessage(), 'bad');
    }
    Web::redirect('index.php?p=nodes');
}

$cluster = Cluster::read($store);
$peers = $store->read('peers');
$seenAt = static function (string $id) use ($peers): ?int {
    $row = $peers[$id] ?? null;
    $at = is_array($row) ? ($row['seen_at'] ?? null) : $row;
    return is_int($at) ? $at : null;
};
$showJson = $identity === null ? '' : json_encode($identity, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

Web::$title = 'Nodes: Death Plan Switch';
?>
<h1>Nodes</h1>

<h2>This node</h2>
<?php if ($identity === null): ?>
<p>This node has no identity yet. On the server, run <code>php cli/node.php init --name=&lt;name&gt;</code>, then reload this page.</p>
<?php else: ?>
<p>Paste this on the primary node's Nodes page to add this node to the cluster.</p>
<pre><?= e($showJson) ?></pre>
<?php endif ?>

<h2>The cluster</h2>
<?php if (count($cluster['nodes']) < 2): ?>
<p>No other node is set up. With one node the switch works as before.</p>
<?php else: ?>
<table>
  <thead><tr><th>Rank</th><th>Name</th><th>Address</th><th>Role</th><th>Last heard</th><th></th></tr></thead>
  <tbody>
<?php
$rows = $cluster['nodes'];
usort($rows, static fn (array $a, array $b): int => $a['rank'] <=> $b['rank']);
foreach ($rows as $n):
    $self = $identity !== null && $n['id'] === $identity['id'];
    ?>
    <tr>
      <td><?= e($n['rank']) ?></td>
      <td><?= e($n['name']) ?><?= $self ? ' (this node)' : '' ?></td>
      <td><?= e($n['url']) ?></td>
      <td><?= $n['id'] === $cluster['primary'] ? 'Primary' : 'Secondary' ?></td>
      <td><?= $self ? '-' : e(when($seenAt($n['id']), $tz)) ?></td>
      <td>
<?php if ($isPrimary && !$self): ?>
        <form method="post" action="index.php?p=nodes"><?= Web::csrfField() ?><input type="hidden" name="action" value="node_remove"><input type="hidden" name="node_id" value="<?= e($n['id']) ?>"><button class="link" type="submit">Remove</button></form>
<?php endif ?>
      </td>
    </tr>
<?php endforeach ?>
  </tbody>
</table>
<dl class="facts">
  <dt>Key shares needed</dt><dd><?= e($cluster['k']) ?></dd>
  <dt>Isolation wait</dt><dd><?= e($cluster['isolation_hours']) ?> hours</dd>
  <dt>Delay between nodes</dt><dd><?= e($cluster['step_delay_min']) ?> minutes</dd>
</dl>
<?php endif ?>

<?php if ($isPrimary && $identity !== null): ?>
<h2>Add a node</h2>
<form method="post" action="index.php?p=nodes">
  <?= Web::csrfField() ?>
  <input type="hidden" name="action" value="node_add">
  <div class="field">
    <label for="identity">Its identity</label>
    <textarea id="identity" name="identity" rows="6" required></textarea>
    <span class="help">The JSON shown under "This node" on the other node.</span>
  </div>
  <div class="field">
    <label for="url">Its address</label>
    <input type="text" id="url" name="url" placeholder="http://192.168.1.20:8080" required>
    <span class="help">https://, or http:// on a private or Tailscale address. This node's own address is taken from Settings.</span>
  </div>
  <div class="field">
    <label for="rank">Its rank</label>
    <input type="number" id="rank" name="rank" min="0" max="9" value="<?= e(count($cluster['nodes']) < 2 ? 1 : count($cluster['nodes'])) ?>" required>
    <span class="help">Lower ranks act first. This node is rank 0 when you add the first peer.</span>
  </div>
  <div class="actions"><button type="submit">Add node</button></div>
</form>

<?php if (count($cluster['nodes']) >= 2): ?>
<h2>Cluster settings</h2>
<form method="post" action="index.php?p=nodes">
  <?= Web::csrfField() ?>
  <input type="hidden" name="action" value="cluster_save">
  <div class="field">
    <label for="k">Key shares needed to open the vault</label>
    <input type="number" id="k" name="k" min="1" max="<?= e(count($cluster['nodes'])) ?>" value="<?= e($cluster['k']) ?>" required>
  </div>
  <div class="field">
    <label for="isolation_hours">Wait when cut off from every other node (hours)</label>
    <input type="number" id="isolation_hours" name="isolation_hours" min="12" max="168" value="<?= e($cluster['isolation_hours']) ?>" required>
  </div>
  <div class="field">
    <label for="step_delay_min">Delay between nodes (minutes)</label>
    <input type="number" id="step_delay_min" name="step_delay_min" min="15" max="240" value="<?= e($cluster['step_delay_min']) ?>" required>
  </div>
  <div class="actions"><button type="submit">Save</button></div>
</form>
<?php endif ?>
<?php elseif (!$isPrimary): ?>
<p>This node mirrors the primary. Add or remove nodes and change these settings there.</p>
<?php endif ?>
