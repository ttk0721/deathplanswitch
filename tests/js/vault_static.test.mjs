import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const files = ['api.js', 'owner.js', 'owner-packages.js', 'recipient.js'];
const banned = /\b(innerHTML|insertAdjacentHTML|outerHTML|document\.write|eval|localStorage|sessionStorage|console\.\w+)\b|new Function|setTimeout\(\s*['"`]/;

for (const f of files) {
  const src = readFileSync(new URL('../../assets/vault/' + f, import.meta.url), 'utf8');
  test(f + ' avoids HTML sinks, storage, logging and eval', () => {
    assert.doesNotMatch(src, banned);
  });
  test(f + ' reads no whole file and sets no window globals', () => {
    assert.doesNotMatch(src, /file\.arrayBuffer\(/);
    assert.doesNotMatch(src, /\bwindow\.\w+\s*=[^=]/);
  });
  test(f + ' imports only relative modules', () => {
    for (const m of src.matchAll(/from\s+'([^']+)'/g)) assert.match(m[1], /^\.\//);
  });
}
