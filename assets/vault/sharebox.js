// Share boxes: ECDH-P256 + HKDF-SHA256 + AES-256-GCM, sealed to a node's P-256 key.
// Only globalThis.crypto, so it runs in browsers and Node 22.
import { b64, unb64, utf8, randomBytes } from './crypto.js';

const subtle = globalThis.crypto.subtle;
const ECDH = { name: 'ECDH', namedCurve: 'P-256' };

/** Share {x, y} to the bytes that get boxed: x (1 byte) then y. */
export function encodeShare(share) {
  const out = new Uint8Array(1 + share.y.length);
  out[0] = share.x;
  out.set(share.y, 1);
  return out;
}

/** Seals bytes to the node's share_pk (b64url SPKI DER). The node id is salt and AAD. */
export async function seal(sharePkB64, nodeId, bytes) {
  const peer = await subtle.importKey('spki', unb64(sharePkB64), ECDH, false, []);
  const eph = await subtle.generateKey(ECDH, true, ['deriveBits']);
  const z = await subtle.deriveBits({ name: 'ECDH', public: peer }, eph.privateKey, 256);
  const hk = await subtle.importKey('raw', z, 'HKDF', false, ['deriveKey']);
  const key = await subtle.deriveKey(
    { name: 'HKDF', hash: 'SHA-256', salt: utf8(nodeId), info: utf8('dps-share-v1') },
    hk, { name: 'AES-GCM', length: 256 }, false, ['encrypt'],
  );
  const iv = randomBytes(12);
  const ct = await subtle.encrypt({ name: 'AES-GCM', iv, additionalData: utf8(nodeId) }, key, bytes);
  const epk = new Uint8Array(await subtle.exportKey('spki', eph.publicKey));
  return { epk: b64(epk), iv: b64(iv), ct: b64(new Uint8Array(ct)) };
}
