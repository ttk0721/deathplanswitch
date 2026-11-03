<?php
declare(strict_types=1);

namespace DPS;

if (!defined('DPS_ROOT')) {
    exit; // only ever loaded by Web::page()
}

Web::requireOwner();
$engine = engine();
$config = $engine->config();
$self = 'index.php?p=vault';

if (Web::posted() && Web::post('action') === 'invite') {
    $vault = new Vault(store());
    $recipient = null;
    foreach ($vault->read()['recipients'] as $candidate) {
        if (($candidate['id'] ?? '') === Web::post('recipient_id')) {
            $recipient = $candidate;
            break;
        }
    }
    $contact = $recipient !== null ? $engine->contacts()->find((string) ($recipient['contact_id'] ?? '')) : null;
    $address = $contact !== null ? trim((string) ($contact['email'] ?? '')) : '';
    if ($address === '') {
        Web::flash(t('That recipient has no contact with an email address, so no invitation was sent.'), 'bad');
    } else {
        $token = $vault->issueInvite((string) $recipient['id']);
        $url = $engine->url($config, 'vault.php', ['invite' => $token]);
        $outbox = new Outbox(store(), new SmtpTransport());
        if ($outbox->send($address, Messages::vaultInvite($config, $url), 'contact', null, false)) {
            Web::flash(t('Invitation sent to {name}.', ['name' => $contact['name']]));
        } else {
            Web::flash(t('The invitation could not be sent. Check the mail settings.'), 'bad');
        }
    }
    Web::redirect($self);
}

// Names and ids only: the page never hands emails or phone numbers to the script.
// A contact not to be told in advance cannot hold packages, so they are not offered.
$people = array_map(
    static fn (array $c): array => ['id' => (string) $c['id'], 'name' => (string) $c['name']],
    array_values(array_filter($engine->contacts()->all(), static fn (array $c): bool => !Contacts::isSilent($c)))
);
$flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

// Federation: the owner's browser needs each node's share key to split the release key.
$clusterAttr = '';
if (Cluster::enabled(store())) {
    $cluster = Cluster::read(store());
    $clusterData = [
        'k' => $cluster['k'],
        'version' => $cluster['version'],
        'nodes' => array_map(
            static fn (array $n): array => [
                'id' => (string) $n['id'],
                'name' => (string) $n['name'],
                'share_pk' => (string) $n['share_pk'],
            ],
            $cluster['nodes']
        ),
    ];
    $clusterAttr = ' data-cluster="' . e(json_encode($clusterData, $flags)) . '"';
}

Web::$title = t('Vault: Death Plan Switch');
?>
<h1><?= e(t('Vault')) ?></h1>
<p class="lede"><?= e(t("What you leave for the people you choose, locked in your browser. Only a recipient's code or password opens it, and only after the switch has fired.")) ?></p>

<div id="vault-owner" class="vault"
     data-api="vault-api.php"
     data-csrf="<?= e(Web::csrf()) ?>"
     data-contacts="<?= e(json_encode($people, $flags)) ?>"
     data-owner-name="<?= e((string) ($config['owner_name'] ?? '')) ?>"
     data-vault-url="<?= e($engine->url($config, 'vault.php')) ?>"<?= $clusterAttr ?>></div>
<noscript><p class="notice notice-warn"><?= e(t('The vault needs JavaScript. Turn it on for this page.')) ?></p></noscript>
<script type="module" src="assets/vault/owner.js?v=<?= e(VERSION) ?>"></script>
