<?php
declare(strict_types=1);
namespace DPS\Tests;
use DPS\{Clock, I18n, Messages};
require __DIR__ . '/lib.php';

$config = ['owner_name' => 'Tomasz', 'timezone' => 'Europe/Warsaw'];

test('pl: the check-in email has a Polish subject and body', function () use ($config): void {
    I18n::setLanguage('pl');
    Clock::set((new \DateTimeImmutable('2026-10-07 13:00', new \DateTimeZone('Europe/Warsaw')))->getTimestamp());
    $m = Messages::checkin($config, 'https://x.test/c', 0, Clock::now() + 3 * 86400, ['name' => 'Kasia'], []);
    same('Potwierdź obecność w Death Plan Switch: 7.10.2026', $m->subject, 'subject');
    check(str_contains($m->text(), 'Kasia dostanie pytanie'), 'next of kin named, Polish body');
    check(!str_contains($m->text(), 'weekly check-in'), 'no English left');
    I18n::setLanguage(null);
});

test('pl: starter templates are Polish and keep the marker', function (): void {
    I18n::setLanguage('pl');
    foreach (['nok', 'technical', 'legal', 'other'] as $kind) {
        $d = Messages::defaults($kind);
        check(!str_contains($d['subject'], 'has stopped'), "$kind subject");
        check(str_contains($d['subject'], '{owner_name}'), "$kind placeholder kept");
        check(!str_contains($d['body'], 'listed you as'), "$kind body is Polish");
    }
    foreach (['nok', 'technical', 'other'] as $kind) {
        check(Messages::hasMarker(Messages::defaults($kind)['body']), "$kind has the marker");
    }
    $v = Messages::verifyDefault();
    check(str_starts_with($v['subject'], 'Czy u '), 'verify subject');
    check(str_contains($v['body'], '{days}'), 'verify placeholder');
    I18n::setLanguage(null);
});

test('pl: the fired email body is the owner\'s own text, untouched', function () use ($config): void {
    I18n::setLanguage('pl');
    $contact = ['kind' => 'nok', 'message' => ['subject' => 'Is {owner_name} all right?', 'body' => 'All fine: see the blue folder.']];
    $vars = Messages::vars($config, ['last_checkin_at' => Clock::now() - 86400], [], null);
    $m = Messages::fired($config, $contact, $vars);
    same('Is Tomasz all right?', $m->subject, 'subject is the owner\'s');
    check(str_contains($m->text(), 'All fine: see the blue folder.'), 'body untouched');
    check(str_contains($m->text(), 'Death Plan Switch to oprogramowanie'), 'footer in Polish');
    I18n::setLanguage(null);
});

test('en: output is unchanged', function () use ($config): void {
    I18n::setLanguage('en');
    check(str_starts_with(Messages::defaults('nok')['subject'], '{owner_name} has stopped'), 'en default');
    $m = Messages::allClear($config);
    same('False alarm: Tomasz has checked in', $m->subject, 'subject');
    I18n::setLanguage(null);
});

test('pl: owner vocabulary, check-in method labels and the answer-link note', function () use ($config): void {
    I18n::setLanguage('pl');
    same('Uzbrojony', I18n::t('Armed'), 'armed word');
    same('Rozbrój przełącznik', I18n::t('Disarm the switch'), 'disarm action');
    same('panel', \DPS\Engine::viaLabel('the dashboard'), 'dashboard via');
    same('link z e-maila', \DPS\Engine::viaLabel('the emailed link'), 'link via');
    same('inny węzeł (link z e-maila)', \DPS\Engine::viaLabel('another node (the emailed link)'), 'peer via');
    $m = Messages::nokVerify($config, ['kind' => 'nok'], ['owner_name' => 'Tomasz'], 'https://x/f', 'https://x/w', Clock::now() + 86400, false);
    check(str_contains($m->text(), 'Z tego linku możesz skorzystać, dopóki sprawa się nie wyjaśni.'), 'link lifetime');
    I18n::setLanguage(null);
});

finish();
