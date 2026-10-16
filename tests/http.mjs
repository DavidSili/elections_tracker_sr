import assert from 'node:assert/strict';

const baseUrl = process.env.TEST_BASE_URL || 'http://127.0.0.1:18080';
const request = (path, options) => fetch(`${baseUrl}${path}`, options);
const results = await (await request('/api.php')).json();
assert.equal(results.mode, 'demo');
assert.equal(Object.keys(results.snapshots).length, 3);

for (const snapshot of Object.values(results.snapshots)) {
  assert.equal(
    Object.values(snapshot.result.allocation.seats).reduce((a, b) => a + b, 0),
    250,
  );
}

const history = await (await request('/api.php?action=history')).json();
assert.ok(history.length >= 9);
const earliest = history[0].capturedAt;
const past = await (await request(`/api.php?at=${encodeURIComponent(earliest)}`)).json();
for (const snapshot of Object.values(past.snapshots).filter(Boolean)) {
  assert.ok(new Date(snapshot.capturedAt) <= new Date(earliest));
}
const empty = await (await request('/api.php?at=2000-01-01T00:00:00Z')).json();
assert.ok(Object.values(empty.snapshots).every((snapshot) => snapshot === null));
assert.equal((await request('/api.php?at=invalid')).status, 400);

for (const path of [
  '/storage/demo/rik/latest.json',
  '/app/bootstrap.php',
  '/bin/console.php',
  '/.env.example',
  '/.git/config',
  '/compose.yaml',
  '/composer.json',
]) {
  const response = await request(path);
  assert.ok([403, 404].includes(response.status), `${path} must be private`);
}

const login = await request('/admin.php');
const html = await login.text();
const cookie = login.headers.get('set-cookie').split(';')[0];
const csrf = html.match(/name="csrf" value="([^"]+)"/)[1];
const headers = { Cookie: cookie };

const unauthorized = await request('/admin.php', {
  method: 'POST',
  headers,
  body: new URLSearchParams({ action: 'save', csrf }),
});
assert.match(await unauthorized.text(), /Prijavite se/);

const success = await request('/admin.php', {
  method: 'POST',
  headers,
  body: new URLSearchParams({ action: 'login', csrf, password: 'Local-test-password-2026' }),
});
assert.match(await success.text(), /Sačuvaj podešavanja/);
const adminCookie = success.headers.get('set-cookie').split(';')[0];

const blocked = await request('/admin.php', {
  method: 'POST',
  headers: { Cookie: adminCookie },
  body: new URLSearchParams({ action: 'demo', csrf: 'incorrect' }),
});
assert.match(await blocked.text(), /Sesija je istekla/);

console.log('HTTP checks passed: results, history, private files, login, authorization, CSRF.');
