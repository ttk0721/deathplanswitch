import { test } from 'node:test';
import assert from 'node:assert/strict';
import * as f from '../../assets/vault/flows.js';
import * as v from '../../assets/vault/crypto.js';
const IT = 1000;
async function world() {
  const { owner, rk, ownerKey } = await f.createVault('a long vault passphrase', IT);
  const a = await f.newRecipient(ownerKey, 'Kim', 'c_1');
  const b = await f.newRecipient(ownerKey, 'Tao', null);
  const p1 = await f.newPackage(ownerKey, rk, 'Passwords', [a.recipient]);
  p1.pkg.manifest = await f.sealManifest(p1.ck, p1.pkg.id, { v: 1, notes: 'bank: blue folder', files: [] });
  const p2 = await f.newPackage(ownerKey, rk, 'Recordings', [b.recipient]);
  p2.pkg.manifest = await f.sealManifest(p2.ck, p2.pkg.id, { v: 1, notes: '', files: [] });
  return { owner, rk, ownerKey, a, b, p1, p2, view: { recipients: [a.recipient, b.recipient], packages: [p1.pkg, p2.pkg] } };
}
test('the owner opens the vault with the passphrase only', async () => {
  const { owner, rk } = await f.createVault('a long vault passphrase', IT);
  assert.equal(owner.iterations, IT);
  assert.deepEqual((await f.openOwner(owner, 'a long vault passphrase')).rk, rk);
  await assert.rejects(f.openOwner(owner, 'wrong passphrase!!'), /wrong passphrase/);
});
test('a recipient opens only their packages, with code and release key', async () => {
  const w = await world();
  assert.match(w.a.recipient.id, /^r_[0-9a-f]{16}$/);
  assert.match(w.p1.pkg.id, /^p_[0-9a-f]{16}$/);
  const got = await f.unlockRecipient(w.view, w.rk, w.a.code);
  assert.equal(got.recipientId, w.a.recipient.id);
  assert.deepEqual(got.packages.map((p) => p.label), ['Passwords']);
  assert.equal((await f.openManifest(got.packages[0].ck, w.p1.pkg)).notes, 'bank: blue folder');
  await assert.rejects(f.unlockRecipient(w.view, v.randomBytes(32), w.a.code), /no match/);
  await assert.rejects(f.unlockRecipient(w.view, w.rk, v.newCode()), /no match/);
});
test('a password set with the code works alongside it', async () => {
  const w = await world();
  const r = await f.addPassword(w.a.recipient, w.a.code, 'family password 2026', IT);
  assert.equal(r.password.iterations, IT);
  const view = { recipients: [r, w.b.recipient], packages: w.view.packages };
  assert.equal((await f.unlockRecipient(view, w.rk, 'family password 2026')).recipientId, r.id);
  assert.equal((await f.unlockRecipient(view, w.rk, w.a.code)).recipientId, r.id);
  await assert.rejects(f.unlockRecipient(view, w.rk, 'family password 2025'), /no match/);
  await assert.rejects(f.addPassword(w.a.recipient, v.newCode(), 'x'.repeat(12), IT), /wrong code/);
});
test('changing recipients rewraps the package key', async () => {
  const w = await world();
  const pkg = await f.setPackageRecipients(w.ownerKey, w.rk, w.p1.pkg, [w.b.recipient]);
  const view = { recipients: w.view.recipients, packages: [pkg] };
  assert.deepEqual((await f.unlockRecipient(view, w.rk, w.b.code)).packages.map((p) => p.id), [pkg.id]);
  await assert.rejects(f.unlockRecipient(view, w.rk, w.a.code), /no match/);
  assert.equal((await f.openManifest(await f.ownerPackageKey(w.ownerKey, pkg), pkg)).notes, 'bank: blue folder');
});
test('files round-trip in chunks and refuse truncation', async () => {
  const ck = await v.aesKey(v.randomBytes(32));
  const data = v.randomBytes(10000);
  const enc = await f.encryptFile(ck, 'f_0123456789abcdef', data, 4096);
  assert.equal(enc.chunks.length, 3);
  assert.deepEqual(await f.decryptFile(ck, 'f_0123456789abcdef', enc.nonce, enc.chunks), data);
  await assert.rejects(f.decryptFile(ck, 'f_0123456789abcdef', enc.nonce, enc.chunks.slice(0, 2)));
  const empty = await f.encryptFile(ck, 'f_0000000000000000', new Uint8Array(0), 4096);
  assert.equal(empty.chunks.length, 1);
  assert.equal((await f.decryptFile(ck, 'f_0000000000000000', empty.nonce, empty.chunks)).length, 0);
});
