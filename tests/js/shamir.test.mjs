import test from 'node:test';
import assert from 'node:assert/strict';
import { split, combine, encodeShare, decodeShare, _mul } from '../../assets/vault/shamir.js';

const rnd = () => crypto.getRandomValues(new Uint8Array(32));
const eq = (a, b) => Buffer.from(a).equals(Buffer.from(b));

function subsets(arr, k, start = 0, cur = [], out = []) {
  if (cur.length === k) { out.push(cur.slice()); return out; }
  for (let i = start; i < arr.length; i++) { cur.push(arr[i]); subsets(arr, k, i + 1, cur, out); cur.pop(); }
  return out;
}

test('every k-subset rebuilds the secret', () => {
  const s = rnd();
  const sh = split(s, 5, 3);
  assert.deepEqual(sh.map((p) => p.x), [1, 2, 3, 4, 5]);
  for (const sub of subsets(sh, 3)) assert.ok(eq(combine(sub), s));
  for (const sub of subsets(sh, 4)) assert.ok(eq(combine(sub), s));
});

test('k-1 shares do not reveal the secret', () => {
  for (let i = 0; i < 100; i++) {
    const s = rnd();
    const sh = split(s, 5, 3);
    assert.ok(!eq(combine(sh.slice(0, 2)), s));
  }
});

test('k = 1 makes every share equal the secret', () => {
  const s = rnd();
  for (const p of split(s, 3, 1)) assert.ok(eq(p.y, s));
});

test('invalid parameters throw', () => {
  const s = rnd();
  assert.throws(() => split(s, 10, 2));
  assert.throws(() => split(s, 2, 3));
  assert.throws(() => split(s, 3, 0));
  assert.throws(() => split([1, 2], 3, 2));
  const sh = split(s, 3, 2);
  assert.throws(() => combine([sh[0], { x: sh[0].x, y: sh[0].y }]));
  assert.throws(() => combine([sh[0], { x: 2, y: new Uint8Array(5) }]));
  assert.throws(() => combine([{ x: 0, y: sh[0].y }, sh[1]]));
  assert.throws(() => combine([]));
});

test('encode/decode round-trip', () => {
  const [p] = split(rnd(), 3, 2);
  const b = encodeShare(p);
  assert.equal(b.length, 33);
  const q = decodeShare(b);
  assert.equal(q.x, p.x);
  assert.ok(eq(q.y, p.y));
  assert.throws(() => decodeShare(new Uint8Array(1)));
  assert.throws(() => decodeShare(new Uint8Array(33)));
});

test('GF(256) multiply', () => {
  assert.equal(_mul(0x57, 0x83), 0xc1);
  assert.equal(_mul(0, 5), 0);
});
