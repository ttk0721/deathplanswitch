// Thin client for vault-api.php. Every request is same-origin and POST/PUT carry the CSRF token.

function url(base, a, query = {}) {
  let out = `${base}?a=${encodeURIComponent(a)}`;
  for (const [k, v] of Object.entries(query)) {
    out += `&${encodeURIComponent(k)}=${encodeURIComponent(v)}`;
  }
  return out;
}

async function send(target, init) {
  const res = await fetch(target, init);
  if (!res.ok) {
    let json = {};
    try {
      json = await res.json();
    } catch (e) {
      json = {};
    }
    throw new Error(json.error || `HTTP ${res.status}`);
  }
  return res;
}

export function client(base, csrf) {
  return {
    async get(a, query = {}) {
      const res = await send(url(base, a, query), { credentials: 'same-origin' });
      return res.json();
    },
    async post(a, body, query = {}) {
      const res = await send(url(base, a, query), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        credentials: 'same-origin',
        body: JSON.stringify(body),
      });
      return res.json();
    },
    async putChunk(fileId, n, bytes) {
      const res = await send(url(base, 'chunk', { file: fileId, n }), {
        method: 'PUT',
        headers: { 'Content-Type': 'application/octet-stream', 'X-CSRF-Token': csrf },
        credentials: 'same-origin',
        body: bytes,
      });
      return res.json();
    },
    async getChunk(fileId, n) {
      const res = await send(url(base, 'chunk', { file: fileId, n }), { credentials: 'same-origin' });
      return new Uint8Array(await res.arrayBuffer());
    },
  };
}
