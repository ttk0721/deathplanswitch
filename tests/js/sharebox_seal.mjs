// CLI: node sharebox_seal.mjs <share_pk> <nodeId> <hex>  -> prints the box JSON.
import { seal } from '../../assets/vault/sharebox.js';

const [pk, nodeId, hex] = process.argv.slice(2);
const bytes = Uint8Array.from(hex.match(/../g) ?? [], (h) => parseInt(h, 16));
console.log(JSON.stringify(await seal(pk, nodeId, bytes)));
