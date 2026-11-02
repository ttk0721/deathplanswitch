// Vault crypto primitives. Only globalThis.crypto, so it runs in browsers and Node 22.
const subtle = globalThis.crypto.subtle;
const enc = new TextEncoder();
const dec = new TextDecoder('utf-8', { fatal: true });

// Crockford base32 alphabet (no I, L, O, U).
const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

export const utf8 = (s) => enc.encode(s);
export const fromUtf8 = (b) => dec.decode(b);

export function randomBytes(n) {
  return globalThis.crypto.getRandomValues(new Uint8Array(n));
}

// base64url, no padding, both ways.
export function b64(bytes) {
  let s = '';
  for (let i = 0; i < bytes.length; i += 0x8000) {
    s += String.fromCharCode(...bytes.subarray(i, i + 0x8000));
  }
  return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

export function unb64(s) {
  if (typeof s !== 'string' || !/^[A-Za-z0-9_-]*$/.test(s) || s.length % 4 === 1) {
    throw new Error('bad base64url');
  }
  const bin = atob(s.replace(/-/g, '+').replace(/_/g, '/'));
  const out = new Uint8Array(bin.length);
  for (let i = 0; i < bin.length; i++) out[i] = bin.charCodeAt(i);
  if (b64(out) !== s) throw new Error('bad base64url'); // reject non-canonical trailing bits
  return out;
}

// 20 random bytes as 32 Crockford characters in 8 groups of 4.
export function newCode() {
  const bytes = randomBytes(20);
  let bits = 0, acc = 0, out = '';
  for (const byte of bytes) {
    acc = (acc << 8) | byte;
    bits += 8;
    while (bits >= 5) {
      out += ALPHABET[(acc >>> (bits - 5)) & 31];
      bits -= 5;
    }
    acc &= (1 << bits) - 1;
  }
  return out.match(/.{4}/g).join('-');
}

// Forgiving read-back: O->0, I/L->1, no spaces or dashes. Null unless exactly 32 valid characters.
export function normalizeCode(s) {
  if (typeof s !== 'string') return null;
  const t = s.toUpperCase().replace(/[\s-]/g, '').replace(/O/g, '0').replace(/[IL]/g, '1');
  if (t.length !== 32) return null;
  const out = new Uint8Array(20);
  let bits = 0, acc = 0, n = 0;
  for (const ch of t) {
    const v = ALPHABET.indexOf(ch);
    if (v < 0) return null;
    acc = (acc << 5) | v;
    bits += 5;
    if (bits >= 8) {
      out[n++] = (acc >>> (bits - 8)) & 255;
      bits -= 8;
      acc &= (1 << bits) - 1;
    }
  }
  return out;
}

export async function hkdfBits(ikm, salt, info, bits = 256) {
  const k = await subtle.importKey('raw', ikm, 'HKDF', false, ['deriveBits']);
  const infoBytes = typeof info === 'string' ? utf8(info) : info;
  return new Uint8Array(await subtle.deriveBits({ name: 'HKDF', hash: 'SHA-256', salt, info: infoBytes }, k, bits));
}

export async function pbkdf2Bits(password, salt, iterations, bits = 256) {
  if (!Number.isInteger(iterations) || iterations < 1 || iterations > 20000000) throw new Error('bad iterations');
  const k = await subtle.importKey('raw', utf8(password), 'PBKDF2', false, ['deriveBits']);
  return new Uint8Array(await subtle.deriveBits({ name: 'PBKDF2', hash: 'SHA-256', salt, iterations }, k, bits));
}

// Non-extractable AES-256-GCM key.
export function aesKey(raw) {
  return subtle.importKey('raw', raw, { name: 'AES-GCM' }, false, ['encrypt', 'decrypt']);
}

export async function keyFromCode(code, salt) {
  return aesKey(await hkdfBits(code, salt, 'dps-code-v1'));
}

export async function keyFromPassword(password, salt, iterations = 600000) {
  return aesKey(await pbkdf2Bits(password, salt, iterations));
}

// KEK needs both the recipient key and the release key.
export async function kek(rkey, rk, packageId) {
  const ikm = new Uint8Array(rkey.length + rk.length);
  ikm.set(rkey, 0);
  ikm.set(rk, rkey.length);
  return aesKey(await hkdfBits(ikm, utf8(packageId), 'dps-kek-v1'));
}

const aadOpt = (aad) => (aad ? { additionalData: aad } : {});

export async function seal(key, bytes, aad) {
  const iv = randomBytes(12);
  const ct = await subtle.encrypt({ name: 'AES-GCM', iv, ...aadOpt(aad) }, key, bytes);
  return { iv: b64(iv), ct: b64(new Uint8Array(ct)) };
}

// Rejects on any tag failure; no partial data.
export async function open(key, sealed, aad) {
  if (!sealed || typeof sealed !== 'object') throw new Error('bad sealed value');
  const iv = unb64(sealed.iv);
  if (iv.length !== 12) throw new Error('bad iv');
  const pt = await subtle.decrypt({ name: 'AES-GCM', iv, ...aadOpt(aad) }, key, unb64(sealed.ct));
  return new Uint8Array(pt);
}

// IV = 8-byte file nonce + uint32 big-endian chunk index; AAD = fileId:index:last.
function chunkParams(nonce, index, last, fileId) {
  if (!(nonce instanceof Uint8Array) || nonce.length !== 8) throw new Error('nonce must be 8 bytes');
  if (!Number.isInteger(index) || index < 0 || index > 0xffffffff) throw new Error('chunk index out of range');
  const iv = new Uint8Array(12);
  iv.set(nonce, 0);
  new DataView(iv.buffer).setUint32(8, index, false);
  return { name: 'AES-GCM', iv, additionalData: utf8(`${fileId}:${index}:${last ? 1 : 0}`) };
}

export async function encryptChunk(key, nonce, index, last, fileId, bytes) {
  return new Uint8Array(await subtle.encrypt(chunkParams(nonce, index, last, fileId), key, bytes));
}

export async function decryptChunk(key, nonce, index, last, fileId, ct) {
  return new Uint8Array(await subtle.decrypt(chunkParams(nonce, index, last, fileId), key, ct));
}
