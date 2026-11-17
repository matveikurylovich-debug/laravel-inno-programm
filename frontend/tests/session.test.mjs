import test from 'node:test';
import assert from 'node:assert/strict';
import { apiFetch } from '../app/lib/api.ts';

function browser() {
  globalThis.window = { location: { pathname: '/orders', href: '/orders' } };
  globalThis.localStorage = { removeItem() {} };
}

test('parallel expired requests share a refresh then retry with cookies', async () => {
  browser();
  let refreshed = false;
  let refreshes = 0;
  let calls = 0;
  globalThis.fetch = async (url, options) => {
    assert.equal(options.credentials, 'same-origin');
    if (url === '/api/auth/refresh') {
      refreshes++;
      await new Promise(resolve => setTimeout(resolve, 5));
      refreshed = true;
      return new Response('{}', { status: 200 });
    }
    calls++;
    assert.equal(new Headers(options.headers).has('Authorization'), false);
    return new Response('{}', { status: refreshed ? 200 : 401 });
  };
  const results = await Promise.all([apiFetch('/api/orders'), apiFetch('/api/orders')]);
  assert.deepEqual(results.map(r => r.status), [200, 200]);
  assert.equal(refreshes, 1);
  assert.equal(calls, 4);
  assert.equal(window.location.href, '/orders');
});

test('refresh outage preserves the browser session', async () => {
  browser();
  globalThis.fetch = async url => new Response('{}', { status: url === '/api/auth/refresh' ? 503 : 401 });
  assert.equal((await apiFetch('/api/orders')).status, 503);
  assert.equal(window.location.href, '/orders');
});

test('revoked refresh redirects a protected request to login', async () => {
  browser();
  globalThis.fetch = async () => new Response('{}', { status: 401 });
  assert.equal((await apiFetch('/api/orders')).status, 401);
  assert.equal(window.location.href, '/login');
});
