// Shamir secret sharing over GF(256), reduction polynomial 0x11b, generator 3.
const EXP = new Uint8Array(512);
const LOG = new Uint8Array(256);
(function init() {
  let v = 1;
  for (let i = 0; i < 255; i++) {
    EXP[i] = v;
    LOG[v] = i;
    let m = v ^ (v << 1); // v * 3
    if (m & 0x100) m ^= 0x11b;
    v = m & 0xff;
  }
  for (let i = 255; i < 512; i++) EXP[i] = EXP[i - 255];
})();

export function _mul(a, b) {
  if (a === 0 || b === 0) return 0;
  return EXP[LOG[a] + LOG[b]];
}

function div(a, b) {
  if (b === 0) throw new Error('division by zero');
  if (a === 0) return 0;
  return EXP[LOG[a] + 255 - LOG[b]];
}

export function split(secret, n, k) {
  if (!(secret instanceof Uint8Array)) throw new Error('secret must be a Uint8Array');
  if (!Number.isInteger(n) || !Number.isInteger(k) || k < 1 || n > 9 || k > n) {
    throw new Error('invalid k/n');
  }
  const shares = [];
  for (let x = 1; x <= n; x++) shares.push({ x, y: new Uint8Array(secret.length) });
  const coef = new Uint8Array(k - 1);
  for (let i = 0; i < secret.length; i++) {
    if (k > 1) crypto.getRandomValues(coef);
    for (const s of shares) {
      let acc = 0;
      for (let j = k - 2; j >= 0; j--) acc = _mul(acc, s.x) ^ coef[j];
      s.y[i] = _mul(acc, s.x) ^ secret[i];
    }
  }
  return shares;
}

export function combine(shares) {
  if (!Array.isArray(shares) || shares.length < 1) throw new Error('no shares');
  const len = shares[0].y.length;
  const seen = new Set();
  for (const s of shares) {
    if (!Number.isInteger(s.x) || s.x < 1 || s.x > 255) throw new Error('bad x');
    if (seen.has(s.x)) throw new Error('duplicate x');
    seen.add(s.x);
    if (!(s.y instanceof Uint8Array) || s.y.length !== len) throw new Error('length mismatch');
  }
  const out = new Uint8Array(len);
  for (let i = 0; i < shares.length; i++) {
    let num = 1;
    let den = 1;
    for (let j = 0; j < shares.length; j++) {
      if (i === j) continue;
      num = _mul(num, shares[j].x);
      den = _mul(den, shares[i].x ^ shares[j].x);
    }
    const l = div(num, den);
    for (let b = 0; b < len; b++) out[b] ^= _mul(shares[i].y[b], l);
  }
  return out;
}

export function encodeShare(share) {
  const out = new Uint8Array(1 + share.y.length);
  out[0] = share.x;
  out.set(share.y, 1);
  return out;
}

export function decodeShare(bytes) {
  if (!(bytes instanceof Uint8Array) || bytes.length < 2 || bytes[0] === 0) throw new Error('bad share');
  return { x: bytes[0], y: bytes.slice(1) };
}
