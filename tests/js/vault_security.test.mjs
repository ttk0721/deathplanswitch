import { test } from 'node:test';
import assert from 'node:assert/strict';
import * as f from '../../assets/vault/flows.js';
import * as v from '../../assets/vault/crypto.js';
const IT = 1000;
const F = 'f_0123456789abcdef';

test('encryptFile rejects non-Uint8Array input and bad chunk sizes', async () => {
  const ck = await v.aesKey(v.randomBytes(32));
  await assert.rejects(f.encryptFile(ck, F, new ArrayBuffer(10)), /Uint8Array/);
  await assert.rejects(f.encryptFile(ck, F, new Uint8Array(5), 0), /chunk size/);
  await assert.rejects(f.encryptFile(ck, F, new Uint8Array(5), 1.5), /chunk size/);
});
test('decryptFile rejects reorder, extra chunk, duplicate, cross-file and cross-key chunks', async () => {
  const ck = await v.aesKey(v.randomBytes(32));
  const e = await f.encryptFile(ck, F, v.randomBytes(9000), 4096);
  const [a, b, c] = e.chunks;
  await assert.rejects(f.decryptFile(ck, F, e.nonce, [b, a, c]));
  await assert.rejects(f.decryptFile(ck, F, e.nonce, [a, b, c, c]));
  await assert.rejects(f.decryptFile(ck, F, e.nonce, [a, b, c, a]));
  await assert.rejects(f.decryptFile(ck, F, e.nonce, [a, b]));
  await assert.rejects(f.decryptFile(ck, F, e.nonce, []));
  await assert.rejects(f.decryptFile(ck, 'f_ffffffffffffffff', e.nonce, e.chunks));
  await assert.rejects(f.decryptFile(ck, F, v.b64(v.randomBytes(8)), e.chunks));
  const e2 = await f.encryptFile(ck, 'f_1111111111111111', v.randomBytes(9000), 4096);
  await assert.rejects(f.decryptFile(ck, F, e.nonce, [a, e2.chunks[1], c]));
  const bad = c.slice(); bad[0] ^= 1;
  await assert.rejects(f.decryptFile(ck, F, e.nonce, [a, b, bad]));
});
test('re-encrypting the same fileId uses a fresh nonce', async () => {
  const ck = await v.aesKey(v.randomBytes(32));
  const d = new Uint8Array(10);
  assert.notEqual((await f.encryptFile(ck, F, d)).nonce, (await f.encryptFile(ck, F, d)).nonce);
});
test('unb64 is canonical and open checks the iv length', async () => {
  assert.throws(() => v.unb64('AB'));
  assert.throws(() => v.unb64('A'));
  assert.throws(() => v.unb64('AA=='));
  assert.throws(() => v.unb64('A+/='));
  const key = await v.aesKey(v.randomBytes(32));
  const s = await v.seal(key, v.utf8('x'));
  await assert.rejects(v.open(key, { iv: v.b64(v.randomBytes(16)), ct: s.ct }), /iv/);
  await assert.rejects(v.open(key, null));
});
test('absurd iterations are refused before any work', async () => {
  await assert.rejects(v.pbkdf2Bits('x', v.randomBytes(16), 1e12), /iterations/);
});
test('swapped wraps, manifests and recipient secrets never open', async () => {
  const { owner, rk, ownerKey } = await f.createVault('a long vault passphrase', IT);
  const a = await f.newRecipient(ownerKey, 'A', null);
  const b = await f.newRecipient(ownerKey, 'B', null);
  const p1 = await f.newPackage(ownerKey, rk, 'one', [a.recipient]);
  const p2 = await f.newPackage(ownerKey, rk, 'two', [b.recipient]);
  p1.pkg.manifest = await f.sealManifest(p1.ck, p1.pkg.id, { v: 1, files: [] });
  p2.pkg.manifest = await f.sealManifest(p2.ck, p2.pkg.id, { v: 1, files: [] });
  const recips = [a.recipient, b.recipient];
  const w = p1.pkg.wraps[a.recipient.id];
  const evil = { ...p2.pkg, wraps: { [a.recipient.id]: w, [b.recipient.id]: w } };
  await assert.rejects(f.unlockRecipient({ recipients: recips, packages: [evil] }, rk, a.code), /no match/);
  await assert.rejects(f.unlockRecipient({ recipients: recips, packages: [evil] }, rk, b.code), /no match/);
  const relab = { ...p1.pkg, wraps: { [b.recipient.id]: w } };
  await assert.rejects(f.unlockRecipient({ recipients: recips, packages: [relab] }, rk, b.code), /no match/);
  await assert.rejects(f.openManifest(p1.ck, { ...p1.pkg, manifest: p2.pkg.manifest }));
  await assert.rejects(f.openManifest(p1.ck, { ...p1.pkg, id: p2.pkg.id }));
  const swapped = { ...b.recipient, code: a.recipient.code };
  await assert.rejects(f.unlockRecipient({ recipients: [swapped], packages: [p2.pkg] }, rk, a.code), /no match/);
  const got = await f.unlockRecipient({ recipients: recips, packages: [p1.pkg, p2.pkg] }, rk, a.code);
  assert.deepEqual(got.packages.map((p) => p.id), [p1.pkg.id]);
  await assert.rejects(f.unlockRecipient({ recipients: recips, packages: [p1.pkg] }, v.randomBytes(32), a.code), /no match/);
  await assert.rejects(f.openOwner({ ...owner, wrapped_rk: owner.check }, 'a long vault passphrase'), /wrong passphrase/);
  await assert.rejects(f.ownerPackageKey(ownerKey, { ...p1.pkg, id: p2.pkg.id }));
});
test('addPassword with a malformed code fails and sets nothing', async () => {
  const { ownerKey } = await f.createVault('a long vault passphrase', IT);
  const a = await f.newRecipient(ownerKey, 'A', null);
  await assert.rejects(f.addPassword(a.recipient, 'nope', 'x'.repeat(12), IT), /wrong code/);
  assert.equal(a.recipient.password, null);
});
