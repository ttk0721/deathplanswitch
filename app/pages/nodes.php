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
        Web::flash(t('Cluster settings are changed on the primary node.'), 'bad');
        Web::redirect('index.php?p=nodes');
    }
    try {
        switch (Web::post('action')) {
            case 'node_add':
                $peer = json_decode(Web::post('identity'), true);
                if (!is_array($peer)) {
                    throw new \InvalidArgumentException(t('Paste the identity JSON shown on the other node.'));
                }
                $url = rtrim(Web::post('url'), '/');
                $publicUrl = trim(Web::post('public_url'));
                $rank = ctype_digit(Web::post('rank')) ? (int) Web::post('rank') : -1;
                $store->locked(static function () use ($store, $identity, $peer, $url, $publicUrl, $rank, $config): void {
                    $cluster = Cluster::read($store);
                    if ($cluster['nodes'] === []) {
                        // The first peer: this node joins the list as rank 0 and primary.
                        $cluster['nodes'][] = [
                            'id' => $identity['id'], 'name' => $identity['name'], 'url' => rtrim((string) ($config['base_url'] ?? ''), '/'),
                            'sign_pk' => $identity['sign_pk'], 'share_pk' => $identity['share_pk'], 'rank' => 0,
                        ];
                        $cluster['primary'] = $identity['id'];
                    }
                    $entry = [
                        'id' => $peer['id'] ?? null, 'name' => $peer['name'] ?? null, 'url' => $url,
                        'sign_pk' => $peer['sign_pk'] ?? null, 'share_pk' => $peer['share_pk'] ?? null, 'rank' => $rank,
                    ];
                    if ($publicUrl !== '') {
                        $entry['public_url'] = $publicUrl;
                    }
                    $cluster['nodes'][] = $entry;
                    Cluster::save($store, $cluster);
                    $store->log('cluster.add', t('A node was added to the cluster.'));
                });
                Web::flash(t('Node added.'));
                break;

            case 'node_public':
                $id = Web::post('node_id');
                $publicUrl = trim(Web::post('public_url'));
                $store->locked(static function () use ($store, $id, $publicUrl): void {
                    $cluster = Cluster::read($store);
                    foreach ($cluster['nodes'] as $i => $n) {
                        if ($n['id'] === $id) {
                            unset($cluster['nodes'][$i]['public_url']);
                            if ($publicUrl !== '') {
                                $cluster['nodes'][$i]['public_url'] = $publicUrl;
                            }
                        }
                    }
                    Cluster::save($store, $cluster);
                    $store->log('cluster.public', t('A node public address was changed.'));
                });
                Web::flash(t('Saved.'));
                break;

            case 'node_remove':
                $id = Web::post('node_id');
                if ($id === $identity['id']) {
                    throw new \InvalidArgumentException(t('This node cannot remove itself.'));
                }
                $store->locked(static function () use ($store, $id): void {
                    $cluster = Cluster::read($store);
                    $cluster['nodes'] = array_values(array_filter($cluster['nodes'], static fn (array $n): bool => $n['id'] !== $id));
                    $cluster['k'] = max(1, min($cluster['k'], count($cluster['nodes'])));
                    Cluster::save($store, $cluster);
                    $store->log('cluster.remove', t('A node was removed from the cluster.'));
                });
                Web::flash(t('Node removed.'), 'warn');
                break;

            case 'cluster_save':
                $store->locked(static function () use ($store): void {
                    $cluster = Cluster::read($store);
                    foreach (['k' => 'k', 'isolation_hours' => 'isolation_hours', 'step_delay_min' => 'step_delay_min'] as $field => $key) {
                        $v = Web::post($field);
                        if (!ctype_digit($v)) {
                            throw new \InvalidArgumentException(t('Enter whole numbers.'));
                        }
                        $cluster[$key] = (int) $v;
                    }
                    Cluster::save($store, $cluster);
                    $store->log('cluster.settings', t('The cluster settings were changed.'));
                });
                Web::flash(t('Saved.'));
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

Web::$title = t('Nodes: Death Plan Switch');
?>
<h1><?= e(t('Nodes')) ?></h1>

<h2><?= e(t('This node')) ?></h2>
<?php if ($identity === null): ?>
<p><?= t('This node has no identity yet. On the server, run {command}, then reload this page.', ['command' => '<code>php cli/node.php init --name=&lt;name&gt;</code>']) ?></p>
<?php else: ?>
<p><?= e(t("Paste this on the primary node's Nodes page to add this node to the cluster.")) ?></p>
<pre><?= e($showJson) ?></pre>
<?php endif ?>

<h2><?= e(t('The cluster')) ?></h2>
<?php if (count($cluster['nodes']) < 2): ?>
<p><?= e(t('No other node is set up. With one node the switch works as before.')) ?></p>
<?php else: ?>
<table>
  <thead><tr><th><?= e(t('Rank')) ?></th><th><?= e(t('Name')) ?></th><th><?= e(t('Address')) ?></th><th><?= e(t('Public address')) ?></th><th><?= e(t('Role')) ?></th><th><?= e(t('Last heard')) ?></th><th></th></tr></thead>
  <tbody>
<?php
$rows = $cluster['nodes'];
usort($rows, static fn (array $a, array $b): int => $a['rank'] <=> $b['rank']);
foreach ($rows as $n):
    $self = $identity !== null && $n['id'] === $identity['id'];
    ?>
    <tr>
      <td><?= e($n['rank']) ?></td>
      <td><?= e($n['name']) ?><?= $self ? ' ' . e(t('(this node)')) : '' ?></td>
      <td><?= e($n['url']) ?></td>
      <td>
<?php if ($isPrimary): ?>
        <form method="post" action="index.php?p=nodes"><?= Web::csrfField() ?><input type="hidden" name="action" value="node_public"><input type="hidden" name="node_id" value="<?= e($n['id']) ?>"><input type="text" name="public_url" value="<?= e($n['public_url'] ?? '') ?>" placeholder="https://" aria-label="<?= e(t('Public address')) ?>"><button class="link" type="submit"><?= e(t('Save')) ?></button></form>
<?php else: ?>
        <?= e($n['public_url'] ?? '-') ?>
<?php endif ?>
      </td>
      <td><?= e($n['id'] === $cluster['primary'] ? t('Primary') : t('Secondary')) ?></td>
      <td><?= $self ? '-' : e(when($seenAt($n['id']), $tz)) ?></td>
      <td>
<?php if ($isPrimary && !$self): ?>
        <form method="post" action="index.php?p=nodes"><?= Web::csrfField() ?><input type="hidden" name="action" value="node_remove"><input type="hidden" name="node_id" value="<?= e($n['id']) ?>"><button class="link" type="submit"><?= e(t('Remove')) ?></button></form>
<?php endif ?>
      </td>
    </tr>
<?php endforeach ?>
  </tbody>
</table>
<dl class="facts">
  <dt><?= e(t('Key shares needed')) ?></dt><dd><?= e($cluster['k']) ?></dd>
  <dt><?= e(t('Isolation wait')) ?></dt><dd><?= e(t('{count} hours', ['count' => $cluster['isolation_hours']])) ?></dd>
  <dt><?= e(t('Delay between nodes')) ?></dt><dd><?= e(t('{count} minutes', ['count' => $cluster['step_delay_min']])) ?></dd>
</dl>
<?php endif ?>

<?php if ($isPrimary && $identity !== null): ?>
<h2><?= e(t('Add a node')) ?></h2>
<form method="post" action="index.php?p=nodes">
  <?= Web::csrfField() ?>
  <input type="hidden" name="action" value="node_add">
  <div class="field">
    <label for="identity"><?= e(t('Its identity')) ?></label>
    <textarea id="identity" name="identity" rows="6" required></textarea>
    <span class="help"><?= e(t('The JSON shown under "This node" on the other node.')) ?></span>
  </div>
  <div class="field">
    <label for="url"><?= e(t('Its address')) ?></label>
    <input type="text" id="url" name="url" placeholder="http://192.168.1.20:8080" required>
    <span class="help"><?= e(t("https://, or http:// on a private or Tailscale address. This node's own address is taken from Settings.")) ?></span>
  </div>
  <div class="field">
    <label for="public_url"><?= e(t('Its public address (optional)')) ?></label>
    <input type="text" id="public_url" name="public_url" placeholder="https://n2.deadman.example">
    <span class="help"><?= e(t("https:// only. Recipients' browsers fetch key shares from here after release.")) ?></span>
  </div>
  <div class="field">
    <label for="rank"><?= e(t('Its rank')) ?></label>
    <input type="number" id="rank" name="rank" min="0" max="9" value="<?= e(count($cluster['nodes']) < 2 ? 1 : count($cluster['nodes'])) ?>" required>
    <span class="help"><?= e(t('Lower ranks act first. This node is rank 0 when you add the first peer.')) ?></span>
  </div>
  <div class="actions"><button type="submit"><?= e(t('Add node')) ?></button></div>
</form>

<?php if (count($cluster['nodes']) >= 2): ?>
<h2><?= e(t('Cluster settings')) ?></h2>
<form method="post" action="index.php?p=nodes">
  <?= Web::csrfField() ?>
  <input type="hidden" name="action" value="cluster_save">
  <div class="field">
    <label for="k"><?= e(t('Key shares needed to open the vault')) ?></label>
    <input type="number" id="k" name="k" min="1" max="<?= e(count($cluster['nodes'])) ?>" value="<?= e($cluster['k']) ?>" required>
  </div>
  <div class="field">
    <label for="isolation_hours"><?= e(t('Wait when cut off from every other node (hours)')) ?></label>
    <input type="number" id="isolation_hours" name="isolation_hours" min="12" max="168" value="<?= e($cluster['isolation_hours']) ?>" required>
  </div>
  <div class="field">
    <label for="step_delay_min"><?= e(t('Delay between nodes (minutes)')) ?></label>
    <input type="number" id="step_delay_min" name="step_delay_min" min="15" max="240" value="<?= e($cluster['step_delay_min']) ?>" required>
  </div>
  <div class="actions"><button type="submit"><?= e(t('Save')) ?></button></div>
</form>
<?php endif ?>
<?php elseif (!$isPrimary): ?>
<p><?= e(t('This node mirrors the primary. Add or remove nodes and change these settings there.')) ?></p>
<?php endif ?>
