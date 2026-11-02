// End-to-end vault check against a running server. Steps: E2E_STEP=owner | recipient.
// State passes between the steps in /app/.e2e-state.json.
import { createHash } from 'node:crypto';
import { readFileSync, writeFileSync } from 'node:fs';
import * as f from '../../assets/vault/flows.js';
import * as c from '../../assets/vault/crypto.js';

const BASE = process.env.E2E_BASE;
const STATE = process.env.E2E_STATE || '/app/.e2e-state.json';
const PASSWORD = 'e2e-password-123';
const jar = new Map();
let failed = false;

const ok = (name) => console.log(`ok    ${name}`);
function check(cond, name) {
  if (cond) ok(name);
  else { console.log(`FAIL  ${name}`); failed = true; }
}

async function req(path, { method = 'GET', headers = {}, body, cookies = true } = {}) {
  if (cookies && jar.size) headers.Cookie = [...jar].map(([k, v]) => `${k}=${v}`).join('; ');
  const res = await fetch(BASE + '/' + path, { method, headers, body, redirect: 'manual' });
  for (const line of res.headers.getSetCookie()) {
    const [pair] = line.split(';');
    const i = pair.indexOf('=');
    jar.set(pair.slice(0, i), pair.slice(i + 1));
  }
  return res;
}
const csrfOf = async (page) => {
  const html = await (await req(page)).text();
  const m = html.match(/name="csrf" value="([0-9a-f]+)"/);
  if (!m) throw new Error('no csrf on ' + page);
  return m[1];
};
const api = (a, extra = {}, opts = {}) => req(`vault-api.php?a=${a}${extra.q || ''}`, opts);
const post = (a, token, obj) => api(a, {}, {
  method: 'POST', headers: { 'X-CSRF-Token': token, 'Content-Type': 'application/json' }, body: JSON.stringify(obj),
});

async function owner() {
  const loginCsrf = await csrfOf('index.php?p=login');
  const res = await req('index.php?p=login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ password: PASSWORD, csrf: loginCsrf }),
  });
  check(res.status === 303, 'owner signs in');
  const token = await csrfOf('index.php?p=settings');

  const { owner: ownerObj, rk, ownerKey } = await f.createVault('e2e vault passphrase', 1000);
  check((await post('init', token, { owner: ownerObj, rk: c.b64(rk) })).status === 200, 'vault initialised');

  const { recipient, code } = await f.newRecipient(ownerKey, 'Kim', null);
  const { pkg, ck } = await f.newPackage(ownerKey, rk, 'Passwords', [recipient]);
  const data = new Uint8Array(9_000_000);
  for (let o = 0; o < data.length; o += 65536) data.set(c.randomBytes(Math.min(65536, data.length - o)), o);
  const fileId = 'f_' + [...c.randomBytes(8)].map((b) => b.toString(16).padStart(2, '0')).join('');
  const enc = await f.encryptFile(ck, fileId, data);
  check(enc.chunks.length === 3, 'file split into 3 chunks');
  for (let i = 0; i < enc.chunks.length; i++) {
    const r = await api('chunk', { q: `&file=${fileId}&n=${i}` }, {
      method: 'PUT', headers: { 'X-CSRF-Token': token }, body: enc.chunks[i],
    });
    check(r.status === 200, `chunk ${i} uploaded`);
  }
  pkg.files = [{ id: fileId, chunks: enc.chunks.length, size: data.length }];
  pkg.manifest = await f.sealManifest(ck, pkg.id, {
    v: 1, notes: 'bank: blue folder',
    files: [{ id: fileId, name: 'big.bin', type: 'application/octet-stream', size: data.length, chunks: enc.chunks.length, nonce: enc.nonce }],
  });
  check((await post('recipient', token, recipient)).status === 200, 'recipient saved');
  check((await post('package', token, pkg)).status === 200, 'package saved');

  const anon = { cookies: false };
  check((await api('public', {}, anon)).status === 403, 'public is closed before release');
  check((await api('release', {}, anon)).status === 403, 'release key is closed before release');
  check((await api('chunk', { q: `&file=${fileId}&n=0` }, anon)).status === 403, 'chunks are closed before release');

  writeFileSync(STATE, JSON.stringify({ code, fileId, sha256: createHash('sha256').update(data).digest('hex'), pid: pkg.id }));
}

async function recipient() {
  const st = JSON.parse(readFileSync(STATE, 'utf8'));
  const anon = { cookies: false };
  const pub = await api('public', {}, anon);
  check(pub.status === 200, 'public opens after release');
  const view = await pub.json();
  const rel = await api('release', {}, anon);
  check(rel.status === 200, 'release key is handed out');
  const rk = c.unb64((await rel.json()).rk);

  let bad = false;
  try { await f.unlockRecipient(view, rk, c.newCode()); } catch (e) { bad = /no match/.test(e.message); }
  check(bad, 'a wrong code opens nothing');

  const got = await f.unlockRecipient(view, rk, st.code);
  check(got.packages.length === 1 && got.packages[0].id === st.pid, 'code unlocks the package');
  const pkg = view.packages.find((p) => p.id === st.pid);
  const manifest = await f.openManifest(got.packages[0].ck, pkg);
  check(manifest.notes === 'bank: blue folder', 'manifest decrypts');

  const file = manifest.files[0];
  const chunks = [];
  for (let i = 0; i < file.chunks; i++) {
    const r = await api('chunk', { q: `&file=${file.id}&n=${i}` }, anon);
    chunks.push(new Uint8Array(await r.arrayBuffer()));
  }
  const plain = await f.decryptFile(got.packages[0].ck, file.id, file.nonce, chunks);
  check(createHash('sha256').update(plain).digest('hex') === st.sha256, 'file decrypts to the same bytes');

  const flipped = chunks.map((x) => x.slice());
  flipped[1][100] ^= 1;
  let rejected = false;
  try { await f.decryptFile(got.packages[0].ck, file.id, file.nonce, flipped); } catch { rejected = true; }
  check(rejected, 'a flipped byte is rejected');
}

try {
  const step = process.env.E2E_STEP;
  if (step === 'owner') await owner();
  else if (step === 'recipient') await recipient();
  else throw new Error('E2E_STEP must be owner or recipient');
} catch (e) {
  console.log('FAIL  ' + (e && e.stack || e));
  failed = true;
}
process.exit(failed ? 1 : 0);
