import { test, beforeEach, afterEach } from 'node:test';
import assert from 'node:assert/strict';
import { client } from '../../assets/vault/api.js';

let calls;
let reply;
const realFetch = globalThis.fetch;

beforeEach(() => {
  calls = [];
  reply = { status: 200, body: { ok: true }, bytes: null };
  globalThis.fetch = async (url, init) => {
    calls.push([url, init]);
    if (reply.bytes) {
      return new Response(reply.bytes, { status: reply.status });
    }
    return new Response(JSON.stringify(reply.body), {
      status: reply.status,
      headers: { 'Content-Type': 'application/json' },
    });
  };
});

afterEach(() => {
  globalThis.fetch = realFetch;
});

test('post sends JSON with the CSRF header and same-origin credentials', async () => {
  const out = await client('vault-api.php', 't').post('init', { owner: 'o', rk: 'r' });
  assert.deepEqual(out, { ok: true });
  assert.equal(calls.length, 1);
  const [url, init] = calls[0];
  assert.equal(url, 'vault-api.php?a=init');
  assert.deepEqual(init, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': 't' },
    credentials: 'same-origin',
    body: JSON.stringify({ owner: 'o', rk: 'r' }),
  });
});

test('get appends encoded query parameters', async () => {
  await client('vault-api.php', 't').get('chunk', { file: 'f_1', n: 2 });
  const [url, init] = calls[0];
  assert.equal(url, 'vault-api.php?a=chunk&file=f_1&n=2');
  assert.equal(init.credentials, 'same-origin');
});

test('putChunk sends the bytes as octet-stream with file and n in the query', async () => {
  await client('vault-api.php', 't').putChunk('f_1', 3, new Uint8Array([1, 2, 3]));
  const [url, init] = calls[0];
  assert.equal(url, 'vault-api.php?a=chunk&file=f_1&n=3');
  assert.equal(init.method, 'PUT');
  assert.deepEqual(init.headers, {
    'Content-Type': 'application/octet-stream',
    'X-CSRF-Token': 't',
  });
  assert.deepEqual(Array.from(init.body), [1, 2, 3]);
});

test('getChunk returns the bytes as a Uint8Array', async () => {
  reply = { status: 200, body: null, bytes: new Uint8Array([9, 8, 7]) };
  const out = await client('vault-api.php', 't').getChunk('f_1', 0);
  assert.ok(out instanceof Uint8Array);
  assert.deepEqual(Array.from(out), [9, 8, 7]);
  assert.equal(calls[0][0], 'vault-api.php?a=chunk&file=f_1&n=0');
});

test('a non-2xx JSON reply throws its error message', async () => {
  reply = { status: 403, body: { error: 'not released' } };
  await assert.rejects(client('vault-api.php', 't').get('chunk', { file: 'f_1', n: 0 }), new Error('not released'));
});

test('a non-2xx reply without an error field throws the HTTP status', async () => {
  reply = { status: 500, body: {} };
  await assert.rejects(client('vault-api.php', 't').post('init', {}), new Error('HTTP 500'));
});
