import test from 'node:test';
import assert from 'node:assert/strict';
import { generateKeyPairSync, createPublicKey, createDecipheriv, diffieHellman, hkdfSync } from 'node:crypto';
import { readFileSync } from 'node:fs';
import { split, combine, _mul } from '../../assets/vault/shamir.js';
import { seal } from '../../assets/vault/sharebox.js';
import { unb64, b64 } from '../../assets/vault/crypto.js';

// Slow reference: carry-less multiply, reduce by 0x11b.
function slowMul(a, b) {
  let r = 0;
  for (let i = 0; i < 8; i++) if (b & (1 << i)) r ^= a << i;
  for (let i = 15; i >= 8; i--) if (r & (1 << i)) r ^= 0x11b << (i - 8);
  return r;
}

test('GF(256) multiplication matches the slow reference for all pairs', () => {
  for (let a = 0; a < 256; a++) for (let b = 0; b < 256; b++) assert.equal(_mul(a, b), slowMul(a, b), `${a}*${b}`);
});

test('every non-zero element has exactly one inverse', () => {
  for (let a = 1; a < 256; a++) {
    let found = 0;
    for (let b = 1; b < 256; b++) if (_mul(a, b) === 1) found++;
    assert.equal(found, 1, 'inverse of ' + a);
  }
});

test('x values are 1..n and splits use fresh coefficients', () => {
  const s = new Uint8Array(32).fill(7);
  const a = split(s, 4, 2);
  const b = split(s, 4, 2);
  assert.deepEqual(a.map((p) => p.x), [1, 2, 3, 4]);
  assert.notDeepEqual(a[0].y, b[0].y);
  const slopes = new Set(Array.from(a[0].y, (v, i) => v ^ s[i]));
  assert.ok(slopes.size > 5, 'per-byte coefficients');
});

test('combine rejects bad input', () => {
  const sh = split(new Uint8Array(8), 3, 2);
  assert.throws(() => combine([sh[0], { x: sh[1].x, y: new Uint8Array(7) }]), /length/);
  assert.throws(() => combine([sh[0], { ...sh[0] }]), /duplicate/);
  assert.throws(() => combine([{ x: 0, y: sh[0].y }, sh[1]]), /bad x/);
  assert.throws(() => combine([{ x: 256, y: sh[0].y }, sh[1]]), /bad x/);
  assert.throws(() => combine([]), /no shares/);
});

test('k-1 shares look uniform and do not give the secret', () => {
  const counts = new Uint32Array(256);
  const N = 25600;
  for (let i = 0; i < N; i++) counts[split(new Uint8Array([0xaa]), 3, 2)[0].y[0]]++;
  const exp = N / 256;
  let chi = 0;
  for (const c of counts) chi += (c - exp) ** 2 / exp;
  assert.ok(chi < 400, 'chi-square ' + chi);
  const s = crypto.getRandomValues(new Uint8Array(32));
  const sh = split(s, 5, 3);
  assert.notDeepEqual(combine(sh.slice(0, 2)), s);
});

test('seal binds the node id and uses a fresh iv and ephemeral key', async () => {
  const { publicKey, privateKey } = generateKeyPairSync('ec', { namedCurve: 'P-256' });
  const pk = b64(publicKey.export({ type: 'spki', format: 'der' }));
  const a = await seal(pk, 'n_00000001', new Uint8Array(33));
  const b = await seal(pk, 'n_00000001', new Uint8Array(33));
  assert.notEqual(a.iv, b.iv);
  assert.notEqual(a.epk, b.epk);
  assert.equal(unb64(a.iv).length, 12);
  const open = (id) => {
    const eph = createPublicKey({ key: Buffer.from(unb64(a.epk)), format: 'der', type: 'spki' });
    const z = diffieHellman({ privateKey, publicKey: eph });
    const key = Buffer.from(hkdfSync('sha256', z, Buffer.from(id), Buffer.from('dps-share-v1'), 32));
    const ct = Buffer.from(unb64(a.ct));
    const d = createDecipheriv('aes-256-gcm', key, Buffer.from(unb64(a.iv)));
    d.setAAD(Buffer.from(id));
    d.setAuthTag(ct.subarray(-16));
    return Buffer.concat([d.update(ct.subarray(0, -16)), d.final()]);
  };
  assert.equal(open('n_00000001').length, 33);
  assert.throws(() => open('n_00000002'));
});

test('seal rejects keys on the wrong curve and garbage', async () => {
  const p384 = generateKeyPairSync('ec', { namedCurve: 'P-384' }).publicKey.export({ type: 'spki', format: 'der' });
  await assert.rejects(seal(b64(p384), 'n_00000001', new Uint8Array(33)));
  await assert.rejects(seal(b64(new Uint8Array(91).fill(1)), 'n_00000001', new Uint8Array(33)));
});

test('the sharing modules never log, store or send key material', () => {
  for (const f of ['shamir.js', 'sharebox.js', 'owner-shares.js']) {
    const src = readFileSync(new URL('../../assets/vault/' + f, import.meta.url), 'utf8');
    assert.doesNotMatch(src, /console\.|localStorage|sessionStorage|fetch\(/);
  }
});

test('recipient fetches other nodes without credentials and enforces distinct x', () => {
  const src = readFileSync(new URL('../../assets/vault/recipient.js', import.meta.url), 'utf8');
  assert.equal([...src.matchAll(/fetch\(/g)].length, 1);
  assert.match(src, /credentials: 'omit'/);
  assert.doesNotMatch(src, /method:\s*'POST'/);
  assert.match(src, /!got\.has\(s\.x\)/);
  assert.match(src, /got\.size < k/);
});
