// The recipient's vault: opens packages with a code or password, or sets a password from an invite.
import { client } from './api.js';
import * as c from './crypto.js';
import * as flows from './flows.js';

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
    return show(message('This link has expired or was used.'));
  }
  const code = field('code', 'Your code, from the card');
  const p1 = field('pw1', 'New password (at least 12 characters)', 'password');
  const p2 = field('pw2', 'New password again', 'password');
  const form = el('form');
  const err = el('div');
  const btn = el('button', 'Set password', { type: 'submit' });
  form.append(code.wrap, p1.wrap, p2.wrap, err, el('div', null, { class: 'actions' }));
  form.lastChild.append(btn);
  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    err.replaceChildren();
    if (p1.input.value.length < 12) return err.replaceChildren(message('Use at least 12 characters.'));
    if (p1.input.value !== p2.input.value) return err.replaceChildren(message('The two passwords differ.'));
    btn.disabled = true;
    try {
      const done = await flows.addPassword({ id: r.id, code: r.code, password: null }, code.input.value, p1.input.value);
      await api.post('password', { password: done.password }, { t: invite });
      show(el('h2', 'Password set'), el('p', 'Your code still works too.'));
    } catch (x) {
      btn.disabled = false;
      err.replaceChildren(message(x.message === 'wrong code' ? 'That code does not match.' : x.message));
    } finally {
      code.input.value = p1.input.value = p2.input.value = '';
    }
  });
  show(el('p', 'Choose a password for opening the vault.'), form);
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
    box.append(el('p', 'This package has nothing in it yet.'));
    return box;
  }
  if (manifest.notes) box.append(el('pre', manifest.notes));
  const ul = el('ul');
  for (const f of manifest.files || []) {
    const li = el('li');
    const btn = el('button', 'Download', { type: 'button', class: 'button-quiet' });
    const status = el('span', '', { class: 'vault-progress' });
    btn.addEventListener('click', async () => {
      btn.disabled = true;
      status.textContent = ' Decrypting...';
      try {
        await download(p.ck, f.id, f);
        status.textContent = ' Done.';
      } catch {
        status.textContent = ' This file could not be opened.';
      }
      btn.disabled = false;
    });
    li.append(el('span', f.name + ' (' + size(f.size) + ') '), btn, status);
    ul.append(li);
  }
  box.append(ul);
  return box;
}

async function open() {
  const status = await api.get('status');
  if (!status.released) return show(el('p', 'Nothing has been released.'));
  const secret = field('secret', 'Your code or password', 'password');
  const form = el('form');
  const err = el('div');
  const btn = el('button', 'Open', { type: 'submit' });
  form.append(secret.wrap, err, el('div', null, { class: 'actions' }));
  form.lastChild.append(btn);
  form.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    err.replaceChildren();
    btn.disabled = true;
    try {
      const view = await api.get('public');
      const rk = c.unb64((await api.get('release')).rk);
      let result;
      try {
        result = await flows.unlockRecipient(view, rk, secret.input.value);
      } catch {
        return err.replaceChildren(message('That code or password does not open anything here.'));
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
  show(el('p', 'Enter the code from your card, or your password.'), form);
}

(invite ? setPassword() : open()).catch((x) => show(message(x.message)));
