<?php
declare(strict_types=1);

namespace DPS;

if (!defined('DPS_ROOT')) {
    exit; // only ever loaded by Web::page()
}

Web::requireOwner();
$tz = (string) (engine()->config()['timezone'] ?? 'UTC');
$rows = store()->history(300);

Web::$title = t('History: Death Plan Switch');
?>
<h1><?= e(t('History')) ?></h1>
<p class="lede"><?= e(t('Everything the switch has done, newest first. Scheduler runs that found nothing to do are not listed.')) ?></p>
<?php if ($rows === []): ?>
<p><?= e(t('Nothing has happened yet.')) ?></p>
<?php else: ?>
<table class="log">
  <tbody>
<?php foreach ($rows as $row): ?>
    <tr>
      <td><?= e(when((int) ($row['at'] ?? 0), $tz, 'j M Y, g:ia')) ?></td>
      <td><?= e((string) ($row['text'] ?? '')) ?></td>
    </tr>
<?php endforeach ?>
  </tbody>
</table>
<?php endif ?>
