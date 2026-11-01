import { test } from 'node:test';
import assert from 'node:assert/strict';
import * as v from '../../assets/vault/crypto.js';
const hex = (b) => Buffer.from(b).toString('hex');

test('HKDF matches RFC 5869 case 1', async () => {
  const okm = await v.hkdfBits(new Uint8Array(22).fill(0x0b), Uint8Array.from({ length: 13 }, (_, i) => i), Uint8Array.from({ length: 10 }, (_, i) => 0xf0 + i), 336);
  assert.equal(hex(okm), '3cb25f25faacd57a90434f64d0362f2a2d2d0a90cf1a5a4c5db02d56ecc4c5bf34007208d5b887185865');
});
test('PBKDF2-SHA256 matches known vectors', async () => {
  assert.equal(hex(await v.pbkdf2Bits('password', v.utf8('salt'), 1)), '120fb6cffcf8b32c43e7225256c4f837a86548c92ccc35480805987cb70be17b');
  assert.equal(hex(await v.pbkdf2Bits('password', v.utf8('salt'), 2)), 'ae4d0c95af6b46d32d0adff928f06dd02a303f8ef3c251dfd6e2d85a95474c43');
});
test('base64url without padding round-trips', () => {
  const b = Uint8Array.from([0, 251, 255, 1]);
  assert.equal(v.b64(b), 'APv_AQ');
  assert.deepEqual(v.unb64('APv_AQ'), b);
});
test('a new code is 8 groups of 4 and reads back to 20 bytes', () => {
  const code = v.newCode();
  assert.match(code, /^[0-9A-HJKMNP-TV-Z]{4}(-[0-9A-HJKMNP-TV-Z]{4}){7}$/);
  assert.equal(v.normalizeCode(code).length, 20);
});
test('codes forgive case, spaces and look-alikes', () => {
  const code = v.newCode();
  const sloppy = code.toLowerCase().replace(/-/g, ' ').replace(/0/g, 'o').replace(/1/g, 'l');
  assert.deepEqual(v.normalizeCode(sloppy), v.normalizeCode(code));
  assert.equal(v.normalizeCode('too short'), null);
  assert.equal(v.normalizeCode(code + 'X'), null);
  assert.equal(v.normalizeCode(code.slice(0, -1) + 'U'), null);
});
test('seal binds key and AAD', async () => {
  const key = await v.aesKey(v.randomBytes(32));
  const s = await v.seal(key, v.utf8('secret'), v.utf8('ctx'));
  assert.equal(v.fromUtf8(await v.open(key, s, v.utf8('ctx'))), 'secret');
  await assert.rejects(v.open(key, s, v.utf8('other')));
  await assert.rejects(v.open(await v.aesKey(v.randomBytes(32)), s, v.utf8('ctx')));
});
test('wrong code or password opens nothing', async () => {
  const salt = v.randomBytes(16);
  const code = v.normalizeCode(v.newCode());
  const s = await v.seal(await v.keyFromCode(code, salt), v.utf8('rkey'));
  assert.equal(v.fromUtf8(await v.open(await v.keyFromCode(code, salt), s)), 'rkey');
  await assert.rejects(v.open(await v.keyFromCode(v.normalizeCode(v.newCode()), salt), s));
  const ps = await v.seal(await v.keyFromPassword('correct horse battery', salt, 1000), v.utf8('rkey'));
  await assert.rejects(v.open(await v.keyFromPassword('wrong horse battery', salt, 1000), ps));
});
test('the package key needs both the recipient key and the release key', async () => {
  const rkey = v.randomBytes(32), rk = v.randomBytes(32), pid = 'p_0123456789abcdef';
  const s = await v.seal(await v.kek(rkey, rk, pid), v.utf8('ck'));
  assert.equal(v.fromUtf8(await v.open(await v.kek(rkey, rk, pid), s)), 'ck');
  await assert.rejects(v.open(await v.kek(rkey, v.randomBytes(32), pid), s));
  await assert.rejects(v.open(await v.kek(rkey, rk, 'p_fedcba9876543210'), s));
});
test('chunks are bound to file, position and end', async () => {
  const key = await v.aesKey(v.randomBytes(32)); const nonce = v.randomBytes(8);
  const c0 = await v.encryptChunk(key, nonce, 0, false, 'f_1', v.utf8('first'));
  const c1 = await v.encryptChunk(key, nonce, 1, true, 'f_1', v.utf8('last'));
  assert.equal(v.fromUtf8(await v.decryptChunk(key, nonce, 0, false, 'f_1', c0)), 'first');
  assert.equal(v.fromUtf8(await v.decryptChunk(key, nonce, 1, true, 'f_1', c1)), 'last');
  await assert.rejects(v.decryptChunk(key, nonce, 1, true, 'f_1', c0));
  await assert.rejects(v.decryptChunk(key, nonce, 0, true, 'f_1', c0));
  await assert.rejects(v.decryptChunk(key, nonce, 0, false, 'f_2', c0));
});
test('bad nonce or index is refused', async () => {
  const key = await v.aesKey(v.randomBytes(32));
  await assert.rejects(v.encryptChunk(key, v.randomBytes(7), 0, true, 'f', v.utf8('x')));
  await assert.rejects(v.encryptChunk(key, v.randomBytes(8), -1, true, 'f', v.utf8('x')));
});
