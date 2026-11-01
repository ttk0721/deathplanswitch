// Owner "Key shares" section: split the release key across the nodes, each share sealed to its node.
// Text only goes in via textContent.
import { split, encodeShare } from './shamir.js';
import { seal } from './sharebox.js';
import { h } from './owner-packages.js';

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
    lines.push(h('p', {}, 'No key shares yet. The release key is held by this node alone.'));
  } else {
    lines.push(h('p', {}, 'Key shares: ' + shares.k + ' of ' + shares.n +
      ' nodes are needed (cluster version ' + shares.cluster_version + ').'));
    if (shares.cluster_version !== cluster.version || shares.k !== cluster.k) {
      lines.push(h('p', { class: 'notice notice-warn' },
        'The cluster changed since the shares were made (now version ' + cluster.version +
        ', ' + cluster.k + ' of ' + nodes.length + '). Distribute the key shares again.'));
    }
  }

  const go = h('button', { type: 'button' }, shares ? 'Distribute key shares again' : 'Distribute key shares');
  go.addEventListener('click', async () => {
    go.disabled = true;
    try {
      say('Sealing the key shares...');
      const n = nodes.length;
      if (n > 9) throw new Error('Key shares support at most 9 nodes.');
      const parts = split(rk, n, cluster.k);
      const boxes = {};
      for (let i = 0; i < n; i++) {
        boxes[nodes[i].id] = await seal(nodes[i].share_pk, nodes[i].id, encodeShare(parts[i]));
      }
      await api.post('shares', { k: cluster.k, n, cluster_version: cluster.version, boxes });
      say('Key shares distributed.');
      await ctx.reload();
    } catch (e) {
      say(e.message || String(e), true);
    } finally { go.disabled = false; }
  });

  root.replaceChildren(h('p', { class: 'help' },
    'The release key is split so that ' + cluster.k + ' of your ' + nodes.length +
    ' nodes must have fired before anyone can open the vault.'),
  ...lines, status, h('div', { class: 'actions' }, go));
}
