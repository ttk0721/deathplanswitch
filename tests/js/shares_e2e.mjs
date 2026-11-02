// End-to-end key shares against two running nodes. Steps: E2E_STEP=owner | recipient | recipient_b_down.
// Env: E2E_A, E2E_B (base URLs), E2E_ID_A/E2E_PK_A, E2E_ID_B/E2E_PK_B (node ids and share_pk).
// State passes between the steps in E2E_STATE.
import { createHash } from 'node:crypto';
import { readFileSync, writeFileSync } from 'node:fs';
import * as f from '../../assets/vault/flows.js';
import * as c from '../../assets/vault/crypto.js';
import * as shamir from '../../assets/vault/shamir.js';
import * as box from '../../assets/vault/sharebox.js';

const A = process.env.E2E_A;
const B = process.env.E2E_B;
const STATE = process.env.E2E_STATE || '/app/.e2e-shares-state.json';
const PASSWORD = 'e2e-password-123';
const jar = new Map();
let failed = false;

const ok = (name) => console.log(`ok    ${name}`);
function check(cond, name) {
  if (cond) ok(name);
  else { console.log(`FAIL  ${name}`); failed = true; }
}

async function req(base, path, { method = 'GET', headers = {}, body, cookies = true } = {}) {
  if (cookies && jar.size) headers.Cookie = [...jar].map(([k, v]) => `${k}=${v}`).join('; ');
  const res = await fetch(base + '/' + path, { method, headers, body, redirect: 'manual' });
  for (const line of res.headers.getSetCookie()) {
    const [pair] = line.split(';');
    const i = pair.indexOf('=');
    jar.set(pair.slice(0, i), pair.slice(i + 1));
  }
  return res;
}
const csrfOf = async (page) => {
  const html = await (await req(A, page)).text();
  const m = html.match(/name="csrf" value="([0-9a-f]+)"/);
  if (!m) throw new Error('no csrf on ' + page);
  return m[1];
};
const api = (a, extra = '', opts = {}) => req(A, `vault-api.php?a=${a}${extra}`, opts);
const post = (a, token, obj) => api(a, '', {
  method: 'POST', headers: { 'X-CSRF-Token': token, 'Content-Type': 'application/json' }, body: JSON.stringify(obj),
});

async function owner() {
  const loginCsrf = await csrfOf('index.php?p=login');
  const res = await req(A, 'index.php?p=login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ password: PASSWORD, csrf: loginCsrf }),
  });
  check(res.status === 303, 'owner signs in on A');
  const token = await csrfOf('index.php?p=settings');

  const { owner: ownerObj, rk, ownerKey } = await f.createVault('e2e vault passphrase', 1000);
  check((await post('init', token, { owner: ownerObj, rk: c.b64(rk) })).status === 200, 'vault initialised');
  const { recipient, code } = await f.newRecipient(ownerKey, 'Kim', null);
  const { pkg, ck } = await f.newPackage(ownerKey, rk, 'Passwords', [recipient]);
  const data = new Uint8Array(300000);
  for (let o = 0; o < data.length; o += 65536) data.set(c.randomBytes(Math.min(65536, data.length - o)), o);
  const fileId = 'f_' + [...c.randomBytes(8)].map((b) => b.toString(16).padStart(2, '0')).join('');
  const enc = await f.encryptFile(ck, fileId, data);
  for (let i = 0; i < enc.chunks.length; i++) {
    const r = await api('chunk', `&file=${fileId}&n=${i}`, { method: 'PUT', headers: { 'X-CSRF-Token': token }, body: enc.chunks[i] });
    check(r.status === 200, `chunk ${i} uploaded`);
  }
  pkg.files = [{ id: fileId, chunks: enc.chunks.length, size: data.length }];
  pkg.manifest = await f.sealManifest(ck, pkg.id, {
    v: 1, notes: 'safe: behind the clock',
    files: [{ id: fileId, name: 'big.bin', type: 'application/octet-stream', size: data.length, chunks: enc.chunks.length, nonce: enc.nonce }],
  });
  check((await post('recipient', token, recipient)).status === 200, 'recipient saved');
  check((await post('package', token, pkg)).status === 200, 'package saved');

  const nodes = [
    { id: process.env.E2E_ID_A, pk: process.env.E2E_PK_A },
    { id: process.env.E2E_ID_B, pk: process.env.E2E_PK_B },
  ];
  const shares = shamir.split(rk, 2, 2);
  const boxes = {};
  for (let i = 0; i < nodes.length; i++) {
    boxes[nodes[i].id] = await box.seal(nodes[i].pk, nodes[i].id, shamir.encodeShare(shares[i]));
  }
  check((await post('shares', token, { k: 2, n: 2, cluster_version: 1, boxes })).status === 200, 'shares saved (2 of 2)');

  writeFileSync(STATE, JSON.stringify({ code, fileId, sha256: createHash('sha256').update(data).digest('hex'), pid: pkg.id }));
}

// The recipient's side: status tells k and the nodes; release is read from each, cross-origin.
async function collect(bDown) {
  const anon = { cookies: false };
  const status = await (await api('status', '', anon)).json();
  check(status.k === 2 && Array.isArray(status.nodes) && status.nodes.length === 2, 'status lists k and both nodes');
  const got = [];
  for (const n of status.nodes) {
    const other = status.nodes.find((x) => x.id !== n.id);
    const origin = new URL(other.url).origin;
    let r;
    try {
      r = await fetch(`${n.url}/vault-api.php?a=release`, { headers: { Origin: origin }, credentials: 'omit' });
    } catch { check(bDown && n.url.startsWith(B), `node ${n.id} unreachable only when B is stopped`); continue; }
    check(r.status === 200, `release from ${n.id}`);
    check(r.headers.get('access-control-allow-origin') === origin, `release from ${n.id} allows the other node's origin`);
    const j = await r.json();
    got.push({ x: j.share.x, y: c.unb64(j.share.y) });
  }
  return { got, view: await (await api('public', '', anon)).json() };
}

async function recipient() {
  const st = JSON.parse(readFileSync(STATE, 'utf8'));
  const { got, view } = await collect(false);
  check(got.length === 2, 'two shares collected');
  const rk = shamir.combine(got);
  const out = await f.unlockRecipient(view, rk, st.code);
  check(out.packages.length === 1 && out.packages[0].id === st.pid, 'code unlocks the package');
  const pkg = view.packages.find((p) => p.id === st.pid);
  const manifest = await f.openManifest(out.packages[0].ck, pkg);
  const file = manifest.files[0];
  const chunks = [];
  for (let i = 0; i < file.chunks; i++) {
    chunks.push(new Uint8Array(await (await api('chunk', `&file=${file.id}&n=${i}`, { cookies: false })).arrayBuffer()));
  }
  const plain = await f.decryptFile(out.packages[0].ck, file.id, file.nonce, chunks);
  check(createHash('sha256').update(plain).digest('hex') === st.sha256, 'file decrypts to the same bytes');
}

async function recipientBDown() {
  const st = JSON.parse(readFileSync(STATE, 'utf8'));
  const { got, view } = await collect(true);
  check(got.length === 1, 'only 1 of 2 shares with B stopped');
  let opened = false;
  try {
    await f.unlockRecipient(view, shamir.combine(got), st.code);
    opened = true;
  } catch { /* expected */ }
  check(!opened, 'one share opens nothing');
}

try {
  const step = process.env.E2E_STEP;
  if (step === 'owner') await owner();
  else if (step === 'recipient') await recipient();
  else if (step === 'recipient_b_down') await recipientBDown();
  else throw new Error('E2E_STEP must be owner, recipient or recipient_b_down');
} catch (e) {
  console.log('FAIL  ' + (e && e.stack || e));
  failed = true;
}
process.exit(failed ? 1 : 0);
