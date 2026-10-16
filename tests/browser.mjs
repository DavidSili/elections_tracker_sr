import assert from 'node:assert/strict';
import { existsSync, mkdirSync } from 'node:fs';
import { chromium } from 'playwright';

const baseUrl = process.env.TEST_BASE_URL || 'http://127.0.0.1:18080';
const candidates = [
  process.env.CHROME_PATH,
  'C:/Program Files/Google/Chrome/Application/chrome.exe',
  'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
].filter(Boolean);
const executablePath = candidates.find((path) => existsSync(path));
const browser = await chromium.launch({ executablePath, headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 1100 } });
const errors = [];
page.on('pageerror', (error) => errors.push(error.message));

try {
  await page.goto(baseUrl);
  await page.waitForSelector('.result-section .seat');
  assert.equal(await page.locator('.result-section').count(), 3);
  assert.equal(await page.locator('.seat').count(), 750);
  assert.equal(await page.locator('.seat-row').count(), 42);
  assert.equal(await page.locator('.donut').count(), 3);
  await page.getByText('Kako računamo mandate?').click();
  mkdirSync('.qa', { recursive: true });
  await page.screenshot({ path: '.qa/desktop.png', fullPage: true });

  const earliest = await page.locator('#history-date').getAttribute('min');
  await page.locator('#history-date').fill(earliest);
  await page.locator('#history-date').dispatchEvent('change');
  const value = await page.locator('#history-time option').nth(1).getAttribute('value');
  await page.locator('#history-time').selectOption(value);
  await page.waitForFunction(() =>
    document.querySelector('#view-label').textContent.includes('Istorijski'),
  );
  await page.getByRole('button', { name: 'Najnovije' }).click();
  await page.waitForFunction(
    () => document.querySelector('#view-label').textContent === 'Najnoviji rezultati',
  );

  await page.setViewportSize({ width: 390, height: 844 });
  await page.screenshot({ path: '.qa/mobile.png', fullPage: true });
  assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth));

  await page.goto(`${baseUrl}/admin.php`);
  await page.getByLabel('Lozinka', { exact: true }).fill('Local-test-password-2026');
  await page.getByRole('button', { name: 'Prijavi se' }).click();
  await page.waitForSelector('#add-list');
  const before = await page.locator('#list-rows tr').count();
  await page.getByRole('button', { name: 'Dodaj listu' }).click();
  assert.equal(await page.locator('#list-rows tr').count(), before + 1);
  await page.getByLabel('Osvežavanje (minuta)').fill('1');
  await page.getByRole('button', { name: 'Sačuvaj podešavanja' }).click();
  await page.getByText('Podešavanja su sačuvana.', { exact: false }).waitFor();
  await page.getByRole('button', { name: 'Generiši nove test podatke' }).click();
  await page.getByText('Novi test rezultati su sačuvani', { exact: false }).waitFor();
  await page.getByRole('button', { name: 'Odjavi se' }).click();
  await page.getByRole('button', { name: 'Prijavi se' }).waitFor();
  assert.deepEqual(errors, []);
  console.log(
    'Browser checks passed: 750 seats, 42 rows, charts, history, mobile, login, settings, demo, logout.',
  );
} finally {
  await browser.close();
}
