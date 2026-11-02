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
test('plural rules pick the right category', function (): void {
    $cases = [
        ['pl', [1 => 'one', 2 => 'few', 5 => 'many', 22 => 'few', 25 => 'many', 12 => 'many', 112 => 'many']],
        ['ru', [1 => 'one', 3 => 'few', 11 => 'many', 21 => 'one', 22 => 'few', 111 => 'many']],
        ['uk', []],
        ['ar', [0 => 'zero', 1 => 'one', 2 => 'two', 3 => 'few', 11 => 'many', 100 => 'other', 102 => 'other', 103 => 'few']],
        ['fr', [0 => 'one', 1 => 'one', 2 => 'other', 1000 => 'other']],
        ['ja', [1 => 'other', 2 => 'other']],
        ['one_other', [0 => 'other', 1 => 'one', 2 => 'other']],
    ];
    $rules = ['uk' => 'ru', 'ja' => 'none'];
    foreach ($cases as [$name, $expected]) {
        foreach ($expected as $n => $category) {
            same($category, \DPS\Plural::category($rules[$name] ?? $name, $n), "$name $n");
        }
    }
    same(0, \DPS\Plural::index('pl', 1), 'pl index one');
    same(2, \DPS\Plural::index('pl', 5), 'pl index many');
    same(5, \DPS\Plural::index('ar', 100), 'ar index other');
    same(6, \DPS\Plural::count('ar'), 'ar forms');
    same(1, \DPS\Plural::count('none'), 'none forms');
});
test('tn() uses the language forms and falls back to English', function (): void {
    I18n::setLanguage('pl');
    same('1 dzień', \DPS\tn('{n} day', '{n} days', 1), 'pl one');
    same('3 dni', \DPS\tn('{n} day', '{n} days', 3), 'pl few');
    same('12 dni', \DPS\tn('{n} day', '{n} days', 12), 'pl many');
    same('1 apple', \DPS\tn('{n} apple', '{n} apples', 1), 'unknown key, one');
    same('5 apples', \DPS\tn('{n} apple', '{n} apples', 5), 'unknown key, other');
    I18n::setLanguage('en');
    same('1 day', \DPS\tn('{n} day', '{n} days', 1), 'en one');
    same('0 days', \DPS\tn('{n} day', '{n} days', 0), 'en zero');
    same('2 days', \DPS\tn('{n} day', '{n} days', 2), 'en other');
});
test('dates data formats ltr and rtl fixtures', function (): void {
    $ltr = [
        'weekdays' => ['Son', 'Mon', 'Die', 'Mit', 'Don', 'Fre', 'Sam'], 'weekdays_short' => ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'],
        'months' => array_map(static fn (int $i): string => "M$i", range(1, 12)),
        'months_short' => array_map(static fn (int $i): string => "m$i", range(1, 12)),
        'hour12' => false, 'am' => '', 'pm' => '',
    ];
    $rtl = [
        'weekdays' => ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'], 'weekdays_short' => ['أحد', 'اثن', 'ثلا', 'أرب', 'خمي', 'جمع', 'سبت'],
        'months' => array_map(static fn (int $i): string => "ش$i", range(1, 12)),
        'months_genitive' => array_map(static fn (int $i): string => "شهر$i", range(1, 12)),
        'months_short' => array_map(static fn (int $i): string => "ش$i", range(1, 12)),
        'hour12' => true, 'am' => 'ص', 'pm' => 'م',
    ];
    $date = new \DateTimeImmutable('2026-11-03 15:05:00 UTC'); // a Tuesday
    same('Die 3 M11 2026, 15:05', \DPS\formatDate($date, 'l j F Y, g:ia', $ltr), 'ltr 24h');
    same('Di 3 m11, 15:05', \DPS\formatDate($date, 'D j M, h:ia', $ltr), 'ltr h');
    same('الثلاثاء 3 شهر11 3:05م', \DPS\formatDate($date, 'l j F g:ia', $rtl), 'rtl 12h with genitive');
    same('ص', \DPS\formatDate(new \DateTimeImmutable('2026-11-03 09:00:00 UTC'), 'a', $rtl), 'rtl am');
    same('3 at 15', \DPS\formatDate($date, 'j \a\t G', $ltr), 'escapes');
});
test('hour labels and named formats follow the language', function (): void {
    I18n::setLanguage('pl');
    same('3 listopada 2026', \DPS\when(gmmktime(9, 0, 0, 11, 3, 2026), 'UTC', 'date'), 'pl date');
    same('wtorek 3 listopada, 9:00', \DPS\when(gmmktime(9, 0, 0, 11, 3, 2026), 'UTC', 'long'), 'pl long');
    I18n::setLanguage('en');
    same('3 November 2026', \DPS\when(gmmktime(9, 0, 0, 11, 3, 2026), 'UTC', 'date'), 'en date');
    same('Tuesday 3 November at 9:00am', \DPS\when(gmmktime(9, 0, 0, 11, 3, 2026), 'UTC', 'long'), 'en long');
    same('12:00pm', \DPS\hourLabel(12), 'noon');
    same('12:00am', \DPS\hourLabel(0), 'midnight');
});
test('the registry drives language choice, direction and Accept-Language', function (): void {
    same(['en', 'pl'], array_keys(I18n::available()), 'available');
    same('Polski', I18n::available()['pl']['name'], 'native name');
    same('pl', I18n::fromAcceptLanguage('pl-PL,pl;q=0.9,en;q=0.8'), 'pl');
    same('en', I18n::fromAcceptLanguage('en-US,en;q=0.9'), 'en');
    same('en', I18n::fromAcceptLanguage('de;q=0.9,en;q=0.5'), 'skips unknown');
    same(null, I18n::fromAcceptLanguage('de,fr;q=0.8'), 'none');
    same(null, I18n::fromAcceptLanguage(''), 'empty');
    I18n::useRegistry([
        'en' => ['name' => 'English', 'dir' => 'ltr', 'plural' => 'one_other'],
        'pl' => ['name' => 'Polski', 'dir' => 'rtl', 'plural' => 'pl'],
        'xx' => ['name' => 'Nowhere', 'dir' => 'ltr', 'plural' => 'none'],
    ]);
    same(['en', 'pl'], array_keys(I18n::available()), 'a registered language without a folder is unavailable');
    I18n::setLanguage('xx');
    same('en', \DPS\lang(), 'no folder means en');
    I18n::setLanguage('pl');
    same('rtl', I18n::dir(), 'dir from the registry');
    I18n::useRegistry(null);
    same('ltr', I18n::dir(), 'real registry');
});
I18n::setLanguage(null);
finish();
