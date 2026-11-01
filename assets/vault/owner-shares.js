// Owner "Key shares" section: split the release key across the nodes, each share sealed to its node.
// Text only goes in via textContent.
import { split, encodeShare } from './shamir.js';
import { seal } from './sharebox.js';
import { h } from './owner-packages.js';
import { tr } from './i18n.js';

export function renderShares(ctx) {
  if (typeof document === 'undefined') return;
  const root = document.getElementById('vault-shares');
  if (!root) return;
  const { api, rk, doc, cluster } = ctx;
  const shares = doc && doc.shares ? doc.shares : null;
  const nodes = (cluster && cluster.nodes) || [];

  const status = h('p', { class: 'help', role: 'status', 'aria-live': 'polite' });
  const say = (msg, bad = false) => {
    status.textContent = msg;
    status.className = bad ? 'notice notice-bad' : 'help';
  };

  const lines = [];
  if (!shares) {
    lines.push(h('p', {}, tr('No key shares yet. The release key is held by this node alone.')));
  } else {
    lines.push(h('p', {}, tr('Key shares: {k} of {n} nodes are needed (cluster version {version}).', { k: shares.k, n: shares.n, version: shares.cluster_version })));
    if (shares.cluster_version !== cluster.version || shares.k !== cluster.k) {
      lines.push(h('p', { class: 'notice notice-warn' },
        tr('The cluster changed since the shares were made (now version {version}, {k} of {n}). Distribute the key shares again.', { version: cluster.version, k: cluster.k, n: nodes.length })));
    }
  }

  const go = h('button', { type: 'button' }, shares ? tr('Distribute key shares again') : tr('Distribute key shares'));
  go.addEventListener('click', async () => {
    go.disabled = true;
    try {
      say(tr('Sealing the key shares...'));
      const n = nodes.length;
      if (n > 9) throw new Error(tr('Key shares support at most 9 nodes.'));
      const parts = split(rk, n, cluster.k);
      const boxes = {};
      for (let i = 0; i < n; i++) {
        boxes[nodes[i].id] = await seal(nodes[i].share_pk, nodes[i].id, encodeShare(parts[i]));
      }
      await api.post('shares', { k: cluster.k, n, cluster_version: cluster.version, boxes });
      say(tr('Key shares distributed.'));
      await ctx.reload();
    } catch (e) {
      say(e.message || String(e), true);
    } finally { go.disabled = false; }
  });

  root.replaceChildren(h('p', { class: 'help' },
    tr('The release key is split so that {k} of your {n} nodes must have fired before anyone can open the vault.', { k: cluster.k, n: nodes.length })),
  ...lines, status, h('div', { class: 'actions' }, go));
}
