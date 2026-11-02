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

const { trn, counted, pluralCategory, pluralIndex } = await import('../../assets/vault/i18n.js');

test('plural rules match the PHP ones', () => {
  const cases = {
    pl: { 1: 'one', 2: 'few', 5: 'many', 22: 'few', 25: 'many', 12: 'many' },
    ru: { 1: 'one', 3: 'few', 11: 'many', 21: 'one' },
    ar: { 0: 'zero', 1: 'one', 2: 'two', 3: 'few', 11: 'many', 100: 'other' },
    fr: { 0: 'one', 1: 'one', 2: 'other' },
    none: { 1: 'other', 2: 'other' },
    one_other: { 0: 'other', 1: 'one', 2: 'other' },
  };
  for (const [rule, byN] of Object.entries(cases)) {
    for (const [n, category] of Object.entries(byN)) assert.equal(pluralCategory(rule, Number(n)), category, `${rule} ${n}`);
  }
  assert.equal(pluralIndex('pl', 5), 2);
  assert.equal(pluralIndex('ar', 100), 5);
});

test('counted phrases use the forms of the language', () => {
  const pl = { dict: { '{n} day': ['{n} dzień', '{n} dni', '{n} dni'] }, rule: 'pl' };
  assert.equal(counted(pl, '{n} day', '{n} days', 1), '1 dzień');
  assert.equal(counted(pl, '{n} day', '{n} days', 3), '3 dni');
  assert.equal(counted(pl, '{n} file', '{n} files', 5), '5 files', 'missing key falls back');
  const wrong = { dict: { '{n} day': ['x', 'y'] }, rule: 'pl' };
  assert.equal(counted(wrong, '{n} day', '{n} days', 2), '2 days', 'wrong form count falls back');
  assert.equal(counted(null, '{n} day', '{n} days', 1), '1 day');
});

test('trn on a Polish page falls back to English for an unknown key', () => {
  assert.equal(trn('{n} thing', '{n} things', 2), '2 things');
});

test('English page keeps English text', () => {
  globalThis.document.documentElement.lang = 'en';
  assert.equal(tr('Open'), 'Open');
  globalThis.document.documentElement.lang = 'pl';
});
