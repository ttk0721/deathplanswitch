// Owner vault page: create or unlock, manage recipients, then hand over to the package screens.
// Keys live only in this closure; nothing goes to globals, storage or URLs.
import { client } from './api.js';
import { b64 } from './crypto.js';
import { createVault, openOwner, newRecipient } from './flows.js';
import { renderPackages, h } from './owner-packages.js';
import { renderShares } from './owner-shares.js';
import { tr } from './i18n.js';

const MIN = 12;

function mount(el) {
  const api = client(el.dataset.api, el.dataset.csrf);
  const csrf = el.dataset.csrf;
  const ownerName = el.dataset.ownerName || tr('the owner');
  const vaultUrl = el.dataset.vaultUrl || '';
  let contacts = [];
  try { contacts = JSON.parse(el.dataset.contacts || '[]'); } catch { contacts = []; }
  let cluster = null;
  if (el.dataset.cluster) {
    try { cluster = JSON.parse(el.dataset.cluster); } catch { cluster = null; }
  }

  const status = h('p', { role: 'status', 'aria-live': 'polite', class: 'help' });
  const body = h('div', {});
  el.replaceChildren(status, body);
  const say = (msg, bad = false) => {
    status.textContent = msg;
    status.className = bad ? 'notice notice-bad' : 'help';
  };
  const guard = (fn) => async (...a) => {
    try { await fn(...a); } catch (e) { say(e.message || String(e), true); }
  };

  const field = (text, type, extra = {}) => {
    const input = h('input', { type, autocomplete: 'off', ...extra });
    return [input, h('label', {}, text + ' ', input)];
  };

  async function start() {
    say(tr('Loading...'));
    const st = await api.get('status');
    say('');
    if (!st.initialised) return showCreate();
    const doc = await api.get('owner');
    showUnlock(doc);
  }

  function showCreate() {
    const [pass, l1] = field(tr('Passphrase (at least {n} characters)', { n: MIN }), 'password', { minLength: MIN });
    const [again, l2] = field(tr('Repeat passphrase'), 'password', { minLength: MIN });
    const go = h('button', { type: 'submit' }, tr('Create the vault'));
    const form = h('form', {},
      h('h2', {}, tr('Create your vault')),
      h('p', { class: 'help' }, tr('This passphrase protects everything in the vault. It cannot be recovered if you lose it.')),
      l1, l2, h('div', { class: 'actions' }, go));
    form.addEventListener('submit', guard(async (ev) => {
      ev.preventDefault();
      if (pass.value.length < MIN) throw new Error(tr('The passphrase needs at least {n} characters.', { n: MIN }));
      if (pass.value !== again.value) throw new Error(tr('The passphrases do not match.'));
      go.disabled = true;
      try {
        say(tr('Creating the vault...'));
        const { owner, rk, ownerKey } = await createVault(pass.value);
        await api.post('init', { owner, rk: b64(rk) });
        pass.value = again.value = '';
        const doc = await api.get('owner');
        say('');
        session(ownerKey, rk, doc);
      } finally { go.disabled = false; }
    }));
    body.replaceChildren(form);
  }

  function showUnlock(doc) {
    const [pass, l1] = field(tr('Passphrase'), 'password');
    const go = h('button', { type: 'submit' }, tr('Unlock'));
    const form = h('form', {}, h('h2', {}, tr('Unlock your vault')), l1, h('div', { class: 'actions' }, go));
    form.addEventListener('submit', guard(async (ev) => {
      ev.preventDefault();
      if (pass.value.length < MIN) throw new Error(tr('The passphrase has at least {n} characters.', { n: MIN }));
      go.disabled = true;
      try {
        say(tr('Unlocking...'));
        const { ownerKey, rk } = await openOwner(doc.owner, pass.value);
        pass.value = '';
        say('');
        session(ownerKey, rk, doc);
      } finally { go.disabled = false; }
    }));
    body.replaceChildren(form);
  }

  function session(ownerKey, rk, doc) {
    const reload = async () => {
      const fresh = await api.get('owner');
      draw(fresh);
    };
    const draw = (d) => {
      body.replaceChildren(...[
        h('section', { class: 'vault' },
          h('h2', {}, tr('Recipients')), recipients(d), h('div', { id: 'vault-card-slot' })),
        cluster ? h('section', { class: 'vault' }, h('h2', {}, tr('Key shares')), h('div', { id: 'vault-shares' })) : null,
        h('section', { class: 'vault' }, h('h2', {}, tr('Packages')), h('div', { id: 'vault-packages' })),
      ].filter(Boolean));
      if (cluster) renderShares({ api, rk, doc: d, cluster, reload });
      renderPackages({ api, ownerKey, rk, doc: d, reload });
    };

    function recipients(d) {
      const wrap = h('div', {});
      const names = new Map(contacts.map((c) => [c.id, c.name]));
      const list = h('ul', { class: 'vault-files' });
      if (!d.recipients.length) wrap.append(h('p', { class: 'help' }, tr('No recipients yet.')));
      for (const r of d.recipients) {
        const del = h('button', { type: 'button', class: 'button-quiet' }, tr('Delete'));
        del.addEventListener('click', guard(async () => {
          if (!window.confirm(tr('Delete recipient "{label}"? Their code stops working.', { label: r.label }))) return;
          await api.post('recipient_delete', { id: r.id });
          await reload();
        }));
        const li = h('li', {}, h('strong', {}, r.label),
          r.contact_id ? ' (' + (names.get(r.contact_id) || tr('unknown contact')) + ')' : '', ' ', del);
        if (r.contact_id) {
          const inv = h('form', { method: 'post', action: 'index.php?p=vault', class: 'inline' },
            h('input', { type: 'hidden', name: 'csrf', value: csrf }),
            h('input', { type: 'hidden', name: 'action', value: 'invite' }),
            h('input', { type: 'hidden', name: 'recipient_id', value: r.id }),
            h('button', { type: 'submit', class: 'button-quiet' }, tr('Invite to set a password')));
          li.append(' ', inv);
        }
        list.append(li);
      }
      wrap.append(list);

      const label = h('input', { type: 'text', maxLength: 80, required: true });
      const sel = h('select', {}, h('option', { value: '' }, tr('No linked contact')),
        contacts.map((c) => h('option', { value: c.id }, c.name)));
      sel.addEventListener('change', () => {
        if (sel.value && !label.value.trim()) label.value = names.get(sel.value) || '';
      });
      const go = h('button', { type: 'submit' }, tr('Add recipient'));
      const form = h('form', {}, h('h3', {}, tr('Add a recipient')),
        h('label', {}, tr('Contact') + ' ', sel), ' ', h('label', {}, tr('Label') + ' ', label),
        h('div', { class: 'actions' }, go));
      form.addEventListener('submit', guard(async (ev) => {
        ev.preventDefault();
        const text = label.value.trim() || names.get(sel.value) || '';
        if (!text) throw new Error(tr('Give the recipient a label.'));
        go.disabled = true;
        try {
          say(tr('Creating the recipient...'));
          const { recipient, code } = await newRecipient(ownerKey, text, sel.value || null);
          await api.post('recipient', recipient);
          const fresh = await api.get('owner');
          draw(fresh);
          say('');
          card(recipient.label, code);
        } finally { go.disabled = false; }
      }));
      wrap.append(form);
      return wrap;
    }

    function card(labelText, code) {
      const slot = document.getElementById('vault-card-slot');
      if (!slot) return;
      const done = h('button', { type: 'button' }, tr('I have printed or saved it'));
      const node = h('div', {},
        h('div', { class: 'vault-card' },
          h('p', {}, labelText),
          h('p', { class: 'vault-code' }, code),
          h('p', {}, vaultUrl),
          h('p', {}, tr('Keep this safe. With the switch\'s release, it opens what {owner} left for you.', { owner: ownerName }))),
        h('p', { class: 'help' }, tr('This code is shown only once.')),
        h('div', { class: 'actions' },
          h('button', { type: 'button', on: { click: () => window.print() } }, tr('Print')), done));
      done.addEventListener('click', () => node.remove());
      slot.replaceChildren(node);
    }

    draw(doc);
  }

  start().catch((e) => say(e.message || String(e), true));
}

if (typeof document !== 'undefined') {
  const el = document.getElementById('vault-owner');
  if (el) mount(el);
}
