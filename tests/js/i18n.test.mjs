import { test } from 'node:test';
import assert from 'node:assert/strict';

globalThis.document = { documentElement: { lang: 'pl' } };
const { tr } = await import('../../assets/vault/i18n.js');

test('translates a known string', () => {
  assert.equal(tr('Open'), 'Otwórz');
});

test('fills placeholders', () => {
  assert.equal(tr('Decrypting {i} of {n}...', { i: 2, n: 5 }), 'Odszyfrowywanie 2 z 5...');
});

test('missing key falls back to English with placeholders filled', () => {
  assert.equal(tr('Nope {x}', { x: 'ok' }), 'Nope ok');
});

test('English page keeps English text', () => {
  globalThis.document.documentElement.lang = 'en';
  assert.equal(tr('Open'), 'Open');
  globalThis.document.documentElement.lang = 'pl';
});
