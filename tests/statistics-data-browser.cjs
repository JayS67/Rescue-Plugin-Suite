/* Requires the disposable WordPress /impact/ page and built-in statistics mode. */
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const base = process.env.RPS_BASE_URL || 'http://127.0.0.1:8086';
(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: process.env.RPS_CHROMIUM || undefined, args: ['--no-sandbox'] });
  try {
    const page = await browser.newPage();
    const requests = [];
    let failure = false;
    await page.route('**/wp-json/plugin/v1/**', async route => {
      const url = new URL(route.request().url());
      if (url.pathname.endsWith('/report')) {
        requests.push(url);
        if (failure) return route.fulfill({ status: 503, json: { error: 'Private upstream diagnostic should not be printed' } });
        return route.fulfill({ json: [{ year: Number(url.searchParams.get('year')), cats_brought_in: 12, cats_adopted: 0, cats_vaccinated: null, note: 'Health totals are unavailable from the standard connection.' }] });
      }
      if (url.pathname.endsWith('/in-care-count')) return route.fulfill({ json: { count: null } });
      return route.fulfill({ json: [] });
    });
    await page.goto(base + '/impact/');
    const root = page.locator('.plugin-stats-ui-wrap').first();
    await root.locator('[id$="_count_brought"]').getByText('12', { exact: true }).waitFor();
    assert.equal(await root.locator('[id$="_count_adopted"]').textContent(), '0', 'A genuine zero is visible');
    assert.equal(await root.locator('[id$="_count_vaccinated"]').textContent(), 'Not available', 'Missing health totals have a clear label instead of a fabricated zero');
    await root.locator('[id$="_count_in_care"]').getByText('Not available', { exact: true }).waitFor();
    assert.match(await root.locator('[role="status"]').textContent(), /not available/);
    const placeholder = await root.locator('[id$="_count_vaccinated"]').evaluate(el => ({ font: parseFloat(getComputedStyle(el).fontSize), number: parseFloat(getComputedStyle(el.closest('[data-rps-ui]').querySelector('[id$="_count_brought"]')).fontSize) }));
    assert(placeholder.font < placeholder.number, 'Unavailable labels do not use oversized counter typography');
    assert.equal(requests.length, 1, 'The initial view fetches only one year');
    assert.match(requests[0].searchParams.get('year'), /^\d{4}$/, 'The selected year is explicit');
    const scripts = await page.locator('script[type="application/ld+json"]').allTextContents();
    assert(!scripts.some(script => /"@type"\s*:\s*"Dataset"/i.test(script)), 'Impact pages do not emit an inapplicable Dataset rich result');
    failure = true;
    await page.reload();
    await root.locator('[role="status"]').getByText('Statistics could not be loaded. Please try again later.', { exact: true }).waitFor();
    assert(!await root.textContent().then(text => text.includes('Private upstream diagnostic')));
    assert.equal(await root.locator('[id$="_count_brought"]').textContent(), 'Not available');
    console.log('Statistics browser regressions passed: lazy year loading, honest missing data, meaningful status and safe failure.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
