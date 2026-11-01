<?php
declare(strict_types=1);
namespace DPS\Tests;
use DPS\Channels;
require __DIR__ . '/lib.php';

test('an Apprise address is accepted and labelled by its scheme', function (): void {
    $c = Channels::normalize(['type' => 'apprise', 'url' => ' discord://123/abc ', 'label' => '']);
    same('discord://123/abc', $c['url'], 'trimmed');
    same('discord', $c['label'], 'label');
    same(null, $c['secret'], 'no secret');
    check(str_starts_with($c['id'], 'ch_') && strlen($c['id']) === 13, 'id');
});
test('Apprise refuses web addresses and junk; webhooks need http(s)', function (): void {
    same(null, Channels::normalize(['type' => 'apprise', 'url' => 'https://x.test/a']), 'https');
    same(null, Channels::normalize(['type' => 'apprise', 'url' => 'not a url']), 'junk');
    same(null, Channels::normalize(['type' => 'webhook', 'url' => 'ftp://x.test']), 'ftp');
    same(null, Channels::normalize(['type' => 'pigeon', 'url' => 'discord://1/2']), 'type');
});
test('a webhook gets a signing secret', function (): void {
    $c = Channels::normalize(['type' => 'webhook', 'url' => 'https://hooks.x.test/in', 'label' => 'My hook']);
    same('My hook', $c['label'], 'label');
    same(32, strlen((string) $c['secret']), 'secret');
});
test('addresses are masked for display', function (): void {
    same('discord://1234…', Channels::masked(['url' => 'discord://123456/abcdef']), 'masked');
});
test('phone numbers keep + and digits', function (): void {
    same('+48600100200', Channels::phone('+48 600-100 200'), 'normalised');
    same('', Channels::phone('call me'), 'none');
});
test('each address gets its own channels, plus SMS from the template', function (): void {
    $o = ['id' => 'ch_o', 'type' => 'apprise', 'url' => 'tgram://o', 'label' => 'tg', 'secret' => null];
    $k = ['id' => 'ch_k', 'type' => 'apprise', 'url' => 'ntfy://k', 'label' => 'ntfy', 'secret' => null];
    $config = ['owner_email' => 'o@x.test', 'backup_email' => 'b@x.test', 'channels' => [$o], 'owner_phone' => '', 'sms_template' => 'serwersms://u:p@{phone}'];
    $contacts = [['id' => 'c_1', 'email' => 'k@x.test', 'phone' => '+48 600 100 200', 'channels' => [$k]], ['id' => 'c_2', 'email' => 't@x.test', 'phone' => '']];
    same([$o], Channels::forAddresses($config, $contacts, ['o@x.test', 'b@x.test']), 'owner once');
    $got = Channels::forAddresses($config, $contacts, ['k@x.test']);
    same(['ch_k', 'sms_c_1'], array_column($got, 'id'), 'kin + sms');
    same('serwersms://u:p@48600100200', $got[1]['url'], 'sms url');
    same([], Channels::forAddresses($config, $contacts, ['t@x.test', 'nobody@x.test']), 'none');
    $config['owner_phone'] = '+48 500 000 001';
    same(['ch_o', 'sms_owner'], array_column(Channels::forAddresses($config, [], ['o@x.test']), 'id'), 'owner sms');
});
finish();
