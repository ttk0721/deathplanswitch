import { test } from 'node:test';
import assert from 'node:assert/strict';
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import { pluralFormCount } from '../../assets/vault/i18n.js';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const dir = path.join(root, 'assets/vault');

// The registry is PHP; read the plural rule per registered code from its text.
const registry = readFileSync(path.join(root, 'lang/languages.php'), 'utf8');
const rules = Object.fromEntries(
  [...registry.matchAll(/'([A-Za-z-]+)'\s*=>\s*\[[^\]]*'plural'\s*=>\s*'(\w+)'/g)].map((m) => [m[1], m[2]]),
);

function used() {
  const out = [];
  const re = /(?<![\w.$])(trn?)\(\s*(['"`])((?:\\.|(?!\2)[^\\])*)\2/g;
  for (const f of readdirSync(dir).filter((n) => n.endsWith('.js') && n !== 'i18n.js').sort()) {
    const src = readFileSync(path.join(dir, f), 'utf8');
    for (const m of src.matchAll(re)) {
      const text = m[3].replace(/\\(.)/g, (_, c) => (c === 'n' ? '\n' : c));
      out.push({ where: `assets/vault/${f}:${src.slice(0, m.index).split('\n').length}`, text, plural: m[1] === 'trn' });
    }
  }
  return out;
}

const holders = (s) => [...new Set([...s.matchAll(/\{([A-Za-z_]\w*)\}/g)].map((m) => m[1]))].sort();

// Every registered language that already has a file; a registered language without one is skipped.
// DPS_I18N_ONLY=de,fr limits the check to those codes.
const only = (process.env.DPS_I18N_ONLY || '').split(',').map((s) => s.trim()).filter(Boolean);
const codes = Object.keys(rules).filter((c) => c !== 'en' && (only.length === 0 || only.includes(c)) &&existsSync(path.join(root, 'assets/lang', c + '.js')));

test('the registered languages are readable', () => {
  assert.ok(rules.en && rules.pl, 'en and pl are registered');
});

for (const code of codes) {
  const file = path.join(root, 'assets/lang', code + '.js');
  const mod = await import(file);
  const dict = mod.default;
  const forms = pluralFormCount(rules[code]);

  test(`[${code}] declares the plural rule from the registry`, () => {
    assert.equal(mod.plural, rules[code]);
  });

  test(`[${code}] every tr()/trn() literal has an entry with the right shape`, () => {
    const calls = used();
    assert.ok(calls.length > 0, 'found tr() calls');
    const bad = [];
    for (const c of calls) {
      const v = dict[c.text];
      if (!Object.hasOwn(dict, c.text)) bad.push(`${c.where}  ${c.text}`);
      else if (c.plural && !(Array.isArray(v) && v.length === forms)) bad.push(`${c.where}  ${c.text}  (needs a list of ${forms} forms)`);
      else if (!c.plural && typeof v !== 'string') bad.push(`${c.where}  ${c.text}  (must be text)`);
    }
    assert.deepEqual(bad, [], `${bad.length} missing or malformed entries`);
  });

  test(`[${code}] no key is defined twice with different values`, () => {
    const src = readFileSync(file, 'utf8');
    const seen = new Map();
    const conflicts = [];
    for (const m of src.matchAll(/^\s*'((?:\\.|[^'\\])*)'\s*:\s*(.*)$/gm)) {
      if (seen.has(m[1]) && seen.get(m[1]) !== m[2]) conflicts.push(m[1]);
      if (!seen.has(m[1])) seen.set(m[1], m[2]);
    }
    assert.deepEqual(conflicts, []);
  });

  test(`[${code}] placeholders match the English and no value is empty`, () => {
    const bad = [];
    for (const [en, value] of Object.entries(dict)) {
      const list = Array.isArray(value) ? value : [value];
      if (Array.isArray(value) && value.length !== forms) bad.push(`${en}  has ${value.length} forms, needs ${forms}`);
      for (const text of list) {
        if (typeof text !== 'string' || text.trim() === '') bad.push(`${en}  has an empty value`);
        else if (JSON.stringify(holders(en)) !== JSON.stringify(holders(text))) bad.push(`${en}  =>  ${text}`);
      }
    }
    assert.deepEqual(bad, []);
  });
}
