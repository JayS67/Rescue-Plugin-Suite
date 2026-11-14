/* Run against the disposable WordPress fixture described in docs/design-and-performance.md. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const { chromium } = require('playwright');
const axePath = require.resolve('axe-core/axe.min.js');
const base = process.env.RPS_BASE_URL || 'http://127.0.0.1:8086';
const output = process.env.RPS_SCREENSHOT_DIR || path.join(os.tmpdir(), 'rps-widget-review');
const animals = [
  { ANIMALID: 42, ANIMALNAME: 'Mittens', ANIMALAGE: '2 years', SEXNAME: 'Female' },
  { ANIMALID: 43, ANIMALNAME: 'Willow', ANIMALAGE: '4 years', SEXNAME: 'Female' },
  { ANIMALID: 44, ANIMALNAME: 'Oliver and his best friend', ANIMALAGE: '1 year', SEXNAME: 'Male' },
].map(a => ({ ...a, SPECIESID: 2, SPECIESNAME: 'Cat', BREEDNAME: 'Domestic shorthair', MOVEMENTDATE: '2026-10-01', ANIMALCOMMENTS: 'A gentle companion looking for a loving home.' }));
const svg = '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="600" viewBox="0 0 600 600"><defs><linearGradient id="a" x2="1" y2="1"><stop stop-color="#e9dfef"/><stop offset="1" stop-color="#c9c7e2"/></linearGradient></defs><rect width="600" height="600" fill="url(#a)"/><path d="M180 290V150l100 80h40l100-80v140c0 100-55 165-120 165S180 390 180 290" fill="#a39ab7"/><ellipse cx="257" cy="300" rx="9" ry="13" fill="#51485e"/><ellipse cx="343" cy="300" rx="9" ry="13" fill="#51485e"/><path d="m287 340 13 10 13-10" fill="none" stroke="#51485e" stroke-width="7"/></svg>';
(async () => {
  fs.mkdirSync(output, { recursive: true });
  const browser = await chromium.launch({ headless: true, ...(process.env.RPS_CHROMIUM ? { executablePath: process.env.RPS_CHROMIUM } : {}) });
  try {
    const page = await browser.newPage({ reducedMotion: 'reduce' });
    const errors = [], runtimeRequests = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('request', request => { if (request.url().includes('cdn.tailwindcss.com')) runtimeRequests.push(request.url()); });
    await page.route('https://service.sheltermanager.com/asmservice**', route => route.fulfill({
      contentType: 'text/javascript',
      body: `document.getElementById('asm3-onlineform').innerHTML = '<form><label for="rps-demo-name">Your name</label><input id="rps-demo-name" autocomplete="name"><label for="rps-demo-email">Email address</label><input id="rps-demo-email" type="email" autocomplete="email"><label for="rps-demo-notes">Tell us about your home</label><textarea id="rps-demo-notes" rows="4"></textarea><button type="button">Send enquiry</button></form>';`,
    }));
    await page.route('**/wp-json/plugin/v1/**', async route => {
      const url = route.request().url();
      if (url.includes('animal-image')) return route.fulfill({ contentType: 'image/svg+xml', body: svg });
      const json = url.includes('/report') ? [{ year: 2026, cats_brought_in: 120, cats_adopted: 96, cats_vaccinated: 110, cats_neutered: 108, cats_chipped: 105 }] : url.includes('in-care-count') ? { count: 24 } : animals;
      return route.fulfill({ json });
    });
    for (const width of [390, 1440]) {
      await page.setViewportSize({ width, height: 1000 });
      for (const [url, selectors] of [
        ['/adopt/', ['#asm-adoptables-widget']], ['/stories/', ['#asm-successstories-widget']],
        ['/impact/', ['.plugin-stats-ui-wrap']],
        ['/widget-review/', ['.asm-featured-animal-widget', '.asm-adoption-quiz-widget', '.plugin-payments-widget', '.rescue-suite-form-wrap']],
      ]) {
        const response = await page.goto(base + url);
        assert.equal(response.status(), 200, url);
        for (const selector of selectors) await page.locator(selector).first().waitFor();
        if (url === '/adopt/') {
          await page.locator('.asm-card-open').first().waitFor();
          await page.locator('.asm-card-open').first().click();
          assert.equal(await page.locator('#asm-modal').getAttribute('aria-hidden'), 'false');
          await page.keyboard.press('Tab');
          assert(await page.locator('#asm-modal').evaluate(el => el.contains(document.activeElement)));
          await page.keyboard.press('Escape');
          await page.waitForFunction(() => document.activeElement?.classList.contains('asm-card-open'));
        }
        if (url === '/stories/') await page.locator('.asm-card[data-animalid]').first().waitFor();
        if (url === '/impact/') {
          await page.waitForFunction(() => document.querySelector('.stat-number')?.textContent === '120');
          assert(await page.locator('.plugin-stats-ui-wrap').getAttribute('aria-describedby'));
          const visibleCards = await page.locator('.counter-card:not(.hidden)').evaluateAll(nodes => nodes.every(el => getComputedStyle(el).opacity === '1' && getComputedStyle(el).visibility === 'visible'));
          assert(visibleCards, 'Impact cards must remain visible with reduced motion');
        }
        if (url === '/widget-review/') {
          await page.waitForFunction(() => [...document.querySelectorAll('[id$="-asm-featured-name"]')].every(el => !el.textContent.includes('Loading')));
          for (const quiz of await page.locator('.asm-adoption-quiz-widget').all()) {
            await quiz.locator('[id$="-asm-quiz-run"]').click();
            await quiz.locator('.rps-match-card').first().waitFor();
          }
          const amount = page.locator('[data-payment-amount]:visible').first();
          await amount.click();
          assert.equal(await amount.getAttribute('aria-pressed'), 'true');
          // Never start a payment or call a live payment gateway.
          assert.equal(await page.locator('.asm-adoption-quiz-widget').count(), 2);
          const duplicateIds = await page.evaluate(() => {
            const ids = [...document.querySelectorAll('.rps-widget [id], .rps-widget[id]')].map(el => el.id);
            return ids.filter((id, i) => ids.indexOf(id) !== i);
          });
          assert.deepEqual(duplicateIds, []);
        }
        await page.addScriptTag({ path: axePath });
        for (const selector of selectors) {
          const violations = await page.evaluate(async selector => {
            const result = await axe.run(selector, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa'] } });
            return result.violations.map(v => ({ id: v.id, nodes: v.nodes.map(n => n.html) }));
          }, selector);
          assert.deepEqual(violations, [], `${width}px ${selector}: ${JSON.stringify(violations)}`);
          await page.locator(selector).first().screenshot({ path: path.join(output, `${selector.replace(/[^a-z0-9-]/gi, '')}-${width}.png`) });
        }
        assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${url} overflows at ${width}px`);
        console.log(`PASS ${width}px ${url}: layout, behaviour and accessibility`);
      }
    }
    assert.deepEqual(errors, [], 'Browser JavaScript errors');
    assert.deepEqual(runtimeRequests, [], 'Browser Tailwind runtime requests');
    console.log(`Screenshots: ${output}`);
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
