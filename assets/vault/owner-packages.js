// Owner screens for packages: notes, recipients and files. Text only goes in via textContent.
import * as c from './crypto.js';
import {
  newPackage, setPackageRecipients, ownerPackageKey, sealManifest, openManifest,
} from './flows.js';
import { tr } from './i18n.js';

const CHUNK = 4194304;

// Tiny DOM builder shared with owner.js: h('div', {class: 'x'}, 'text', child).
export function h(tag, attrs = {}, ...kids) {
  const n = document.createElement(tag);
  for (const [k, v] of Object.entries(attrs)) {
    if (v === false || v == null) continue;
    if (k === 'on') for (const [ev, fn] of Object.entries(v)) n.addEventListener(ev, fn);
    else if (k === 'class') n.className = v;
    else if (k in n && k !== 'list') n[k] = v;
    else n.setAttribute(k, v === true ? '' : v);
  }
  for (const k of kids.flat()) if (k != null && k !== false) n.append(k);
  return n;
}

export function humanSize(n) {
  if (n < 1024) return n + ' B';
  const u = ['KiB', 'MiB', 'GiB', 'TiB'];
  let i = -1;
  do { n /= 1024; i++; } while (n >= 1024 && i < u.length - 1);
  return n.toFixed(n < 10 ? 1 : 0) + ' ' + u[i];
}

const fileId = () => 'f_' + [...c.randomBytes(8)].map((b) => b.toString(16).padStart(2, '0')).join('');

export function renderPackages(ctx) {
  if (typeof document === 'undefined') return;
  const root = document.getElementById('vault-packages');
  if (!root) return;
  const { api, ownerKey, rk, doc, reload } = ctx;
  root.replaceChildren();

  const status = h('p', { class: 'help', role: 'status', 'aria-live': 'polite' });
  const say = (msg, bad = false) => {
    status.textContent = msg;
    status.className = bad ? 'notice notice-bad' : 'help';
  };
  const guard = (fn) => async (...a) => {
    try { await fn(...a); } catch (e) { say(e.message || String(e), true); }
  };
  const recipientsById = (ids) => doc.recipients.filter((r) => ids.includes(r.id));
  const checks = (selected) => doc.recipients.map((r) => {
    const box = h('input', { type: 'checkbox', value: r.id, checked: selected.includes(r.id) });
    return h('label', { class: 'check' }, box, ' ', r.label);
  });
  const picked = (labels) => labels.map((l) => l.querySelector('input')).filter((i) => i.checked).map((i) => i.value);

  // Create form
  const label = h('input', { type: 'text', maxLength: 80, required: true });
  const boxes = checks([]);
  const create = h('form', { class: 'vault-new' },
    h('h3', {}, tr('New package')),
    h('label', {}, tr('Label') + ' ', label),
    doc.recipients.length ? h('fieldset', {}, h('legend', {}, tr('Recipients')), boxes) : h('p', { class: 'help' }, tr('Add a recipient first.')),
    h('div', { class: 'actions' }, h('button', { type: 'submit' }, tr('Create package'))));
  create.addEventListener('submit', guard(async (ev) => {
    ev.preventDefault();
    const name = label.value.trim();
    if (!name) throw new Error(tr('Give the package a label.'));
    say(tr('Creating...'));
    const { pkg, ck } = await newPackage(ownerKey, rk, name, recipientsById(picked(boxes)));
    pkg.manifest = await sealManifest(ck, pkg.id, { v: 1, notes: '', files: [] });
    await api.post('package', pkg);
    await reload();
  }));
  root.append(create, status);

  const list = h('div', { class: 'vault-packages-list' });
  root.append(h('h3', {}, tr('Packages')), list);
  if (!doc.packages.length) list.append(h('p', { class: 'help' }, tr('No packages yet.')));
  for (const pkg of doc.packages) list.append(packageRow(pkg));

  function packageRow(pkg) {
    const body = h('div', { class: 'vault-package-body' });
    let opened = false;
    const toggle = h('button', { type: 'button', class: 'button-quiet' }, tr('Open'));
    const del = h('button', { type: 'button', class: 'button-quiet' }, tr('Delete'));
    toggle.addEventListener('click', guard(async () => {
      if (opened) { body.replaceChildren(); opened = false; toggle.textContent = tr('Open'); return; }
      say(tr('Opening...'));
      const ck = await ownerPackageKey(ownerKey, pkg);
      const manifest = await openManifest(ck, pkg);
      renderBody(body, pkg, ck, manifest);
      opened = true;
      toggle.textContent = tr('Close');
      say('');
    }));
    del.addEventListener('click', guard(async () => {
      if (!window.confirm(tr('Delete package "{label}" and its files?', { label: pkg.label }))) return;
      await api.post('package_delete', { id: pkg.id });
      await reload();
    }));
    return h('section', { class: 'vault-package' },
      h('h4', {}, pkg.label),
      h('p', { class: 'help' }, tr('{recipients} recipient(s), {files} file(s)', { recipients: pkg.recipients.length, files: pkg.files.length })),
      h('div', { class: 'actions' }, toggle, del), body);
  }

  async function save(pkg, ck, manifest, extra = {}) {
    const next = { ...pkg, ...extra, manifest: await sealManifest(ck, pkg.id, manifest) };
    await api.post('package', next);
  }

  function renderBody(body, pkg, ck, manifest) {
    const notes = h('textarea', { rows: 6, maxLength: 20000, value: manifest.notes || '' });
    const saveNotes = h('button', { type: 'button' }, tr('Save notes'));
    saveNotes.addEventListener('click', guard(async () => {
      manifest.notes = notes.value;
      await save(pkg, ck, manifest);
      say(tr('Notes saved.'));
    }));

    const rboxes = checks(pkg.recipients);
    const saveRec = h('button', { type: 'button' }, tr('Save recipients'));
    saveRec.addEventListener('click', guard(async () => {
      say(tr('Re-wrapping keys...'));
      const next = await setPackageRecipients(ownerKey, rk, pkg, recipientsById(picked(rboxes)));
      await save(next, ck, manifest);
      await reload();
    }));

    const files = h('ul', { class: 'vault-files' });
    for (const f of manifest.files) {
      const dl = h('button', { type: 'button', class: 'button-quiet' }, tr('Download'));
      const rm = h('button', { type: 'button', class: 'button-quiet' }, tr('Remove'));
      dl.addEventListener('click', guard(() => download(ck, f, dl)));
      rm.addEventListener('click', guard(async () => {
        if (!window.confirm(tr('Remove "{name}"?', { name: f.name }))) return;
        manifest.files = manifest.files.filter((x) => x.id !== f.id);
        await save(pkg, ck, manifest, { files: pkg.files.filter((x) => x.id !== f.id) });
        await reload();
      }));
      files.append(h('li', {}, h('span', {}, f.name + ' (' + humanSize(f.size) + ') '), dl, ' ', rm));
    }

    const picker = h('input', { type: 'file', multiple: true });
    const progress = h('div', {});
    const add = h('button', { type: 'button' }, tr('Upload'));
    add.addEventListener('click', guard(async () => {
      const chosen = [...picker.files];
      if (!chosen.length) throw new Error(tr('Choose a file first.'));
      add.disabled = true;
      try {
        const done = [];
        for (const file of chosen) done.push(await upload(ck, file, progress));
        manifest.files = [...manifest.files, ...done.map((d) => d.m)];
        await save(pkg, ck, manifest, { files: [...pkg.files, ...done.map((d) => d.p)] });
        await reload();
      } finally { add.disabled = false; }
    }));

    body.replaceChildren(
      h('label', {}, tr('Notes') + ' ', notes), h('div', { class: 'actions' }, saveNotes),
      h('fieldset', {}, h('legend', {}, tr('Recipients')), rboxes), h('div', { class: 'actions' }, saveRec),
      h('h5', {}, tr('Files')), files,
      h('div', {}, picker, ' ', add), progress);
  }

  async function upload(ck, file, host) {
    const id = fileId();
    const nonce = c.randomBytes(8);
    const total = Math.max(1, Math.ceil(file.size / CHUNK));
    const bar = h('progress', { class: 'vault-progress', max: total, value: 0 });
    host.append(h('div', {}, file.name + ' ', bar));
    for (let i = 0; i < total; i++) {
      const bytes = new Uint8Array(await file.slice(i * CHUNK, (i + 1) * CHUNK).arrayBuffer());
      const ct = await c.encryptChunk(ck, nonce, i, i === total - 1, id, bytes);
      await api.putChunk(id, i, ct);
      bar.value = i + 1;
    }
    return {
      m: { id, name: file.name, type: file.type || 'application/octet-stream', size: file.size, chunks: total, nonce: c.b64(nonce) },
      p: { id, chunks: total, size: file.size },
    };
  }

  async function download(ck, f, btn) {
    btn.disabled = true;
    try {
      const nonce = c.unb64(f.nonce);
      const parts = [];
      for (let i = 0; i < f.chunks; i++) {
        say(tr('Decrypting {i} of {n}...', { i: i + 1, n: f.chunks }));
        parts.push(await c.decryptChunk(ck, nonce, i, i === f.chunks - 1, f.id, await api.getChunk(f.id, i)));
      }
      const url = URL.createObjectURL(new Blob(parts, { type: f.type }));
      const a = h('a', { href: url, download: f.name });
      document.body.append(a);
      a.click();
      a.remove();
      setTimeout(() => URL.revokeObjectURL(url), 1000);
      say(tr('Downloaded {name}.', { name: f.name }));
    } finally { btn.disabled = false; }
  }
}
