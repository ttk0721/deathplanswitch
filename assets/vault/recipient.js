// The recipient's vault: opens packages with a code or password, or sets a password from an invite.
import { client } from './api.js';
import * as c from './crypto.js';
import * as flows from './flows.js';
import { combine } from './shamir.js';
import { tr } from './i18n.js';

const root = document.getElementById('vault-recipient');
const api = client(root.dataset.api, '');
const invite = root.dataset.invite;

function el(tag, text, attrs = {}) {
  const n = document.createElement(tag);
  if (text != null) n.textContent = text;
  for (const [k, v] of Object.entries(attrs)) n.setAttribute(k, v);
  return n;
}
function field(id, label, type = 'text') {
  const wrap = el('div', null, { class: 'field' });
  const input = el('input', null, { id, type, autocomplete: 'off', required: '' });
  wrap.append(el('label', label, { for: id }), input);
  return { wrap, input };
}
function show(...nodes) { root.replaceChildren(...nodes); }
function message(text, kind = 'bad') {
  const n = el('div', null, { class: 'notice notice-' + kind, role: 'status' });
  n.append(el('p', text));
  return n;
}
function size(n) {
  if (n < 1024) return n + ' B';
  if (n < 1048576) return (n / 1024).toFixed(1) + ' KB';
  return (n / 1048576).toFixed(1) + ' MB';
}

async function setPassword() {
  let r;
  try {
    r = (await api.get('invite', { t: invite })).recipient;
  } catch {
    return show(message(tr('This link has expired or was used.')));
  }
  const code = field('code', tr('Your code, from the card'));
  const p1 = field('pw1', tr('New password (at least 12 characters)'), 'password');
  const p2 = field('pw2', tr('New password again'), 'password');
  const form = el('form');
  const err = el('div');
  const btn = el('button', tr('Set password'), { type: 'submit' });
  form.append(code.wrap, p1.wrap, p2.wrap, err, el('div', null, { class: 'actions' }));
  form.lastChild.append(btn);
  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    err.replaceChildren();
    if (p1.input.value.length < 12) return err.replaceChildren(message(tr('Use at least 12 characters.')));
    if (p1.input.value !== p2.input.value) return err.replaceChildren(message(tr('The two passwords differ.')));
    btn.disabled = true;
    try {
      const done = await flows.addPassword({ id: r.id, code: r.code, password: null }, code.input.value, p1.input.value);
      await api.post('password', { password: done.password }, { t: invite });
      show(el('h2', tr('Password set')), el('p', tr('Your code still works too.')));
    } catch (x) {
      btn.disabled = false;
      err.replaceChildren(message(x.message === 'wrong code' ? tr('That code does not match.') : x.message));
    } finally {
      code.input.value = p1.input.value = p2.input.value = '';
    }
  });
  show(el('p', tr('Choose a password for opening the vault.')), form);
}

async function download(ck, fileId, file) {
  const parts = [];
  const nonce = c.unb64(file.nonce);
  for (let n = 0; n < file.chunks; n++) {
    const ct = await api.getChunk(fileId, n);
    parts.push(await c.decryptChunk(ck, nonce, n, n === file.chunks - 1, fileId, ct));
  }
  const url = URL.createObjectURL(new Blob(parts, { type: file.type || 'application/octet-stream' }));
  const a = el('a', null, { href: url, download: file.name });
  document.body.append(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

async function renderPackage(p, raw) {
  const box = el('section', null, { class: 'vault-files' });
  box.append(el('h2', p.label));
  let manifest;
  try {
    manifest = await flows.openManifest(p.ck, raw);
  } catch {
    box.append(el('p', tr('This package has nothing in it yet.')));
    return box;
  }
  if (manifest.notes) box.append(el('pre', manifest.notes));
  const ul = el('ul');
  for (const f of manifest.files || []) {
    const li = el('li');
    const btn = el('button', tr('Download'), { type: 'button', class: 'button-quiet' });
    const status = el('span', '', { class: 'vault-progress' });
    btn.addEventListener('click', async () => {
      btn.disabled = true;
      status.textContent = ' ' + tr('Decrypting...');
      try {
        await download(p.ck, f.id, f);
        status.textContent = ' ' + tr('Done.');
      } catch {
        status.textContent = ' ' + tr('This file could not be opened.');
      }
      btn.disabled = false;
    });
    li.append(el('span', f.name + ' (' + size(f.size) + ') '), btn, status);
    ul.append(li);
  }
  box.append(ul);
  return box;
}

function validX(x) { return Number.isInteger(x) && x >= 1 && x <= 255; }

function shareOf(r) {
  if (!r || !r.share || !Number.isInteger(r.share.x)) return null;
  return { x: r.share.x, y: c.unb64(r.share.y) };
}

async function remoteShare(base) {
  const ctl = new AbortController();
  const timer = setTimeout(() => ctl.abort(), 10000);
  try {
    const res = await fetch(`${base.replace(/\/$/, '')}/vault-api.php?a=release`, {
      mode: 'cors', credentials: 'omit', signal: ctl.signal,
    });
    if (!res.ok) return null;
    return shareOf(await res.json());
  } catch {
    return null;
  } finally {
    clearTimeout(timer);
  }
}

// Returns the release key, or throws an Error with a user-facing message.
async function releaseKey(status) {
  if (!status.k) return c.unb64((await api.get('release')).rk);
  const k = status.k;
  const got = new Map();
  try {
    const own = shareOf(await api.get('release'));
    if (own && validX(own.x)) got.set(own.x, own);
  } catch { /* counted as missing */ }
  const others = (status.nodes || []).filter((n) => {
    try { return new URL(n.url).origin !== location.origin; } catch { return false; }
  });
  for (const n of others) {
    if (got.size >= k) break;
    const s = await remoteShare(n.url);
    if (s && validX(s.x) && !got.has(s.x)) got.set(s.x, s);
  }
  if (got.size < k) {
    throw new Error(tr('Only {got} of the {k} needed nodes answered. The vault cannot be opened yet. Try again later.', { got: got.size, k }));
  }
  return combine([...got.values()].slice(0, k));
}

async function open() {
  const status = await api.get('status');
  if (!status.released) return show(el('p', tr('Nothing has been released.')));
  const secret = field('secret', tr('Your code or password'), 'password');
  const form = el('form');
  const err = el('div');
  const btn = el('button', tr('Open'), { type: 'submit' });
  form.append(secret.wrap, err, el('div', null, { class: 'actions' }));
  form.lastChild.append(btn);
  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    err.replaceChildren();
    btn.disabled = true;
    try {
      const view = await api.get('public');
      const rk = await releaseKey(status);
      let result;
      try {
        result = await flows.unlockRecipient(view, rk, secret.input.value);
      } catch {
        return err.replaceChildren(message(tr('That code or password does not open anything here.')));
      }
      const nodes = [];
      for (const p of result.packages) {
        nodes.push(await renderPackage(p, view.packages.find((x) => x.id === p.id)));
      }
      show(...nodes);
    } catch (x) {
      err.replaceChildren(message(x.message));
    } finally {
      secret.input.value = '';
      btn.disabled = false;
    }
  });
  show(el('p', tr('Enter the code from your card, or your password.')), form);
}

(invite ? setPassword() : open()).catch((x) => show(message(x.message)));
