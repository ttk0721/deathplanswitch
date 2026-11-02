import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readdirSync, readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import pl from '../../assets/lang/pl.js';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const dir = path.join(root, 'assets/vault');

function used() {
  const out = [];
  const re = /(?<![\w.$])tr\(\s*(['"`])((?:\\.|(?!\1)[^\\])*)\1/g;
  for (const f of readdirSync(dir).filter((n) => n.endsWith('.js')).sort()) {
    const src = readFileSync(path.join(dir, f), 'utf8');
    for (const m of src.matchAll(re)) {
      const text = m[2].replace(/\\(.)/g, (_, c) => (c === 'n' ? '\n' : c));
      out.push({ where: `assets/vault/${f}:${src.slice(0, m.index).split('\n').length}`, text });
    }
  }
  return out;
}

const holders = (s) => [...new Set([...s.matchAll(/\{([A-Za-z_]\w*)\}/g)].map((m) => m[1]))].sort();

test('every tr() literal has a Polish entry', () => {
  const calls = used();
  assert.ok(calls.length > 0, 'found tr() calls');
  const missing = calls.filter((c) => !Object.hasOwn(pl, c.text)).map((c) => `${c.where}  ${c.text}`);
  assert.deepEqual(missing, [], `${missing.length} missing Polish entries`);
});

test('no key is defined twice with different values', () => {
  const src = readFileSync(path.join(root, 'assets/lang/pl.js'), 'utf8');
  const seen = new Map();
  const conflicts = [];
  for (const m of src.matchAll(/^\s*'((?:\\.|[^'\\])*)'\s*:\s*'((?:\\.|[^'\\])*)'/gm)) {
    if (seen.has(m[1]) && seen.get(m[1]) !== m[2]) conflicts.push(m[1]);
    if (!seen.has(m[1])) seen.set(m[1], m[2]);
  }
  assert.deepEqual(conflicts, []);
});

test('placeholders match between English and Polish', () => {
  const bad = Object.entries(pl)
    .filter(([en, p]) => JSON.stringify(holders(en)) !== JSON.stringify(holders(p)))
    .map(([en, p]) => `${en}  =>  ${p}`);
  assert.deepEqual(bad, []);
});
