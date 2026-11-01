<?php
declare(strict_types=1);
namespace DPS\Tests;
require __DIR__ . '/lib.php';

use DPS\I18n;
use DPS\Clock;

test('en returns the text unchanged', function (): void {
    I18n::setLanguage('en');
    same('Sign in', \DPS\t('Sign in'), 'en');
    same('en', \DPS\lang(), 'lang');
});
test('pl lookup, placeholders and fallback', function (): void {
    I18n::setLanguage('pl');
    same('pl', \DPS\lang(), 'lang');
    same('nigdy', \DPS\t('never'), 'lookup');
    same('Last ran 5 minutes ago.', \DPS\t('Last ran {when}.', ['when' => '5 minutes ago']), 'fallback with vars');
    same('a 3 b', \DPS\t('a {n} b', ['n' => 3]), 'int var');
});
test('unknown language becomes en', function (): void {
    I18n::setLanguage('xx');
    same('en', \DPS\lang(), 'unknown');
});
test('relative and duration in Polish with plurals', function (): void {
    I18n::setLanguage('pl');
    same('minuta', \DPS\duration(60), 'min1');
    same('5 minut', \DPS\duration(300), 'min5');
    same('2 godziny', \DPS\duration(7200), 'h2');
    same('5 godzin', \DPS\duration(5 * 3600), 'h5');
    same('3 dni', \DPS\duration(3 * 86400), 'd3');
    same('12 dni', \DPS\duration(12 * 86400), 'd12');
    same('22 dni', \DPS\duration(22 * 86400), 'd22');
    $now = Clock::now();
    same('za 5 minut', \DPS\relative($now + 300), 'future');
    same('5 minut temu', \DPS\relative($now - 300), 'past');
    same('przed chwilą', \DPS\relative($now + 5), 'now');
    same('nigdy', \DPS\relative(null), 'never');
});
test('English relative and duration are unchanged', function (): void {
    I18n::setLanguage('en');
    same('a minute', \DPS\duration(60), 'a minute');
    same('5 minutes', \DPS\duration(300), 'min');
    same('3 days', \DPS\duration(3 * 86400), 'days');
    same('never', \DPS\relative(null), 'never');
    same('just now', \DPS\relative(Clock::now()), 'now');
    same('in 5 minutes', \DPS\relative(Clock::now() + 300), 'future');
    same('5 minutes ago', \DPS\relative(Clock::now() - 300), 'past');
});
test('when() speaks Polish dates and keeps English unchanged', function (): void {
    $ts = gmmktime(9, 0, 0, 11, 3, 2026);
    I18n::setLanguage('pl');
    same('wtorek 3 listopada 2026, 9:00', \DPS\when($ts, 'UTC'), 'default');
    same('3 listopada 2026', \DPS\when($ts, 'UTC', 'j F Y'), 'date');
    same('9:00', \DPS\when($ts, 'UTC', 'g:ia'), 'time');
    same('3 lis 2026, 15:05', \DPS\when(gmmktime(15, 5, 0, 11, 3, 2026), 'UTC', 'j M Y, g:ia'), 'short');
    same('niedziela 1 lutego, 0:30', \DPS\when(gmmktime(0, 30, 0, 2, 1, 2026), 'UTC', 'l j F, G:i'), 'sunday');
    same('nigdy', \DPS\when(null), 'never');
    I18n::setLanguage('en');
    same('Tuesday 3 November 2026, 9:00am', \DPS\when($ts, 'UTC'), 'en default');
});
test('history entries are written in the current language', function (): void {
    $transport = new class implements \DPS\Transport {
        public function send(array $smtp, array $to, string $fromName, string $replyTo, \DPS\Email $email): void {}
    };
    $dir = sys_get_temp_dir() . '/dps-i18n-' . bin2hex(random_bytes(5));
    mkdir($dir, 0700);
    $store = new \DPS\Store($dir);
    $store->write('config', ['owner_name' => 'Sam', 'owner_email' => 'o@x.test', 'smtp' => ['from_email' => 's@x.test']]);
    $outbox = new \DPS\Outbox($store, $transport, new class implements \DPS\Notifier {
        public function notify(array $channel, \DPS\Notice $notice, string $event): void {}
    });
    I18n::setLanguage('pl');
    $outbox->send('o@x.test', new \DPS\Email('Hello'));
    I18n::setLanguage('en');
    $outbox->send('o@x.test', new \DPS\Email('Hello'));
    $rows = $store->history();
    same('Sent "Hello" to o@x.test', $rows[0]['text'], 'English entry unchanged');
    same('Wysłano „Hello” do: o@x.test', $rows[1]['text'], 'Polish entry');
});
test('message placeholder descriptions have Polish', function (): void {
    I18n::setLanguage('pl');
    foreach (\DPS\Messages::PLACEHOLDERS as $code => $meaning) {
        check(\DPS\t($meaning) !== $meaning, 'Polish for ' . $code);
    }
});
test('hour labels are 24-hour in Polish', function (): void {
    I18n::setLanguage('pl');
    same('9:00', \DPS\hourLabel(9), 'pl');
    I18n::setLanguage('en');
    same('9:00am', \DPS\hourLabel(9), 'en');
});
I18n::setLanguage(null);
finish();
