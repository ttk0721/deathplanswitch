<?php
declare(strict_types=1);
namespace DPS\Tests;
use DPS\{Email, Notice};
require __DIR__ . '/lib.php';

test('a notice has the subject and first link, never the message', function (): void {
    $email = (new Email('Please check in'))->p('Private words')->link('Check in', 'https://s.test/c?t=1')->link('Other', 'https://s.test/x');
    $n = Notice::fromEmail($email, 'o@x.test');
    same('Please check in', $n->title, 'title');
    same('The full message was sent by email to o@x.test.', $n->body, 'body');
    same('https://s.test/c?t=1', $n->link, 'link');
    same("The full message was sent by email to o@x.test.\nhttps://s.test/c?t=1", $n->text(), 'text');
    check(!str_contains($n->text(), 'Private'), 'no message text');
});
test('no link, no link line', function (): void {
    $n = Notice::fromEmail((new Email('Armed'))->p('x'), 'o@x.test');
    same(null, $n->link, 'link');
    same($n->body, $n->text(), 'text');
    same(null, (new Email('a'))->firstLink(), 'firstLink');
});
finish();
