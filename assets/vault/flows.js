// Vault operations on the stored shapes. Secrets stay inside; only the documented returns leave.
import * as c from './crypto.js';

const id = (p) => p + '_' + [...c.randomBytes(8)].map((b) => b.toString(16).padStart(2, '0')).join('');
const parts = (s) => ({ iv: s.iv, ct: s.ct });

export async function createVault(passphrase, iterations = 600000) {
  const rk = c.randomBytes(32);
  const salt = c.randomBytes(16);
  const ownerKey = await c.keyFromPassword(passphrase, salt, iterations);
  const owner = {
    salt: c.b64(salt),
    iterations,
    wrapped_rk: await c.seal(ownerKey, rk, c.utf8('dps-rk')),
    check: await c.seal(ownerKey, c.utf8('dps-owner-ok'), c.utf8('dps-owner')),
  };
  return { owner, rk, ownerKey };
}

export async function openOwner(owner, passphrase) {
  const ownerKey = await c.keyFromPassword(passphrase, c.unb64(owner.salt), owner.iterations);
  try {
    await c.open(ownerKey, owner.check, c.utf8('dps-owner'));
    return { ownerKey, rk: await c.open(ownerKey, owner.wrapped_rk, c.utf8('dps-rk')) };
  } catch {
    throw new Error('wrong passphrase');
  }
}

export async function newRecipient(ownerKey, label, contactId) {
  const rid = id('r');
  const rkey = c.randomBytes(32);
  const code = c.newCode();
  const salt = c.randomBytes(16);
  const sealed = await c.seal(await c.keyFromCode(c.normalizeCode(code), salt), rkey, c.utf8(rid));
  const recipient = {
    id: rid,
    contact_id: contactId ?? null,
    label,
    code: { salt: c.b64(salt), ...parts(sealed) },
    password: null,
    owner_wrapped: await c.seal(ownerKey, rkey, c.utf8(rid)),
  };
  return { recipient, code };
}

// Opens the recipient key with the code wrap; null when the code is wrong.
async function rkeyFromCode(r, codeBytes) {
  try {
    return await c.open(await c.keyFromCode(codeBytes, c.unb64(r.code.salt)), r.code, c.utf8(r.id));
  } catch {
    return null;
  }
}

export async function addPassword(recipient, code, password, iterations = 600000) {
  const bytes = c.normalizeCode(code);
  const rkey = bytes && (await rkeyFromCode(recipient, bytes));
  if (!rkey) throw new Error('wrong code');
  const salt = c.randomBytes(16);
  const sealed = await c.seal(await c.keyFromPassword(password, salt, iterations), rkey, c.utf8(recipient.id));
  return { ...recipient, password: { salt: c.b64(salt), iterations, ...parts(sealed) } };
}

async function wrapFor(ownerKey, rk, pid, raw, recipients) {
  const wraps = {};
  for (const r of recipients) {
    const rkey = await c.open(ownerKey, r.owner_wrapped, c.utf8(r.id));
    wraps[r.id] = await c.seal(await c.kek(rkey, rk, pid), raw, c.utf8(pid + ':' + r.id));
  }
  return wraps;
}

export async function newPackage(ownerKey, rk, label, recipients) {
  const pid = id('p');
  const raw = c.randomBytes(32);
  const pkg = {
    id: pid,
    label,
    recipients: recipients.map((r) => r.id),
    owner_wrapped_ck: await c.seal(ownerKey, raw, c.utf8(pid)),
    wraps: await wrapFor(ownerKey, rk, pid, raw, recipients),
    manifest: null,
    files: [],
  };
  return { pkg, ck: await c.aesKey(raw) };
}

export async function setPackageRecipients(ownerKey, rk, pkg, recipients) {
  const raw = await c.open(ownerKey, pkg.owner_wrapped_ck, c.utf8(pkg.id));
  return {
    ...pkg,
    recipients: recipients.map((r) => r.id),
    wraps: await wrapFor(ownerKey, rk, pkg.id, raw, recipients),
  };
}

export async function ownerPackageKey(ownerKey, pkg) {
  return c.aesKey(await c.open(ownerKey, pkg.owner_wrapped_ck, c.utf8(pkg.id)));
}

export function sealManifest(ck, pkgId, manifest) {
  return c.seal(ck, c.utf8(JSON.stringify(manifest)), c.utf8(pkgId));
}

export async function openManifest(ck, pkg) {
  return JSON.parse(c.fromUtf8(await c.open(ck, pkg.manifest, c.utf8(pkg.id))));
}

// The last chunk is flagged in its AAD, so truncation fails to decrypt.
export async function encryptFile(ck, fileId, bytes, chunkSize = 4194304) {
  if (!(bytes instanceof Uint8Array)) throw new Error('bytes must be a Uint8Array');
  if (!Number.isInteger(chunkSize) || chunkSize < 1) throw new Error('bad chunk size');
  const nonce = c.randomBytes(8);
  const n = Math.max(1, Math.ceil(bytes.length / chunkSize));
  const chunks = [];
  for (let i = 0; i < n; i++) {
    const part = bytes.subarray(i * chunkSize, (i + 1) * chunkSize);
    chunks.push(await c.encryptChunk(ck, nonce, i, i === n - 1, fileId, part));
  }
  return { nonce: c.b64(nonce), chunks };
}

export async function decryptFile(ck, fileId, nonce, chunks) {
  if (!chunks.length) throw new Error('no chunks');
  const nb = c.unb64(nonce);
  const parts = [];
  let total = 0;
  for (let i = 0; i < chunks.length; i++) {
    const p = await c.decryptChunk(ck, nb, i, i === chunks.length - 1, fileId, chunks[i]);
    parts.push(p);
    total += p.length;
  }
  const out = new Uint8Array(total);
  let off = 0;
  for (const p of parts) { out.set(p, off); off += p.length; }
  return out;
}

export async function unlockRecipient(view, rk, secret) {
  const codeBytes = c.normalizeCode(secret);
  for (const r of view.recipients) {
    const keys = [];
    if (codeBytes) keys.push(await rkeyFromCode(r, codeBytes));
    if (r.password) {
      try {
        const pk = await c.keyFromPassword(secret, c.unb64(r.password.salt), r.password.iterations);
        keys.push(await c.open(pk, r.password, c.utf8(r.id)));
      } catch { /* not this password */ }
    }
    for (const rkey of keys) {
      if (!rkey) continue;
      const packages = [];
      for (const p of view.packages) {
        if (!p.wraps || !p.wraps[r.id]) continue;
        try {
          const raw = await c.open(await c.kek(rkey, rk, p.id), p.wraps[r.id], c.utf8(p.id + ':' + r.id));
          packages.push({ id: p.id, label: p.label, ck: await c.aesKey(raw) });
        } catch { /* wrong release key for this package */ }
      }
      if (packages.length) return { recipientId: r.id, packages };
    }
  }
  throw new Error('no match');
}
