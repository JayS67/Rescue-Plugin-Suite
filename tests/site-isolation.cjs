/* Against the disposable WP fixture used by test:widgets. Never submits a form or payment. */
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const base = process.env.RPS_BASE_URL || 'http://127.0.0.1:8086';
(async () => {
  const browser = await chromium.launch({ headless: true, ...(process.env.RPS_CHROMIUM ? { executablePath: process.env.RPS_CHROMIUM } : {}) });
  try {
    const page = await browser.newPage({ viewport: { width: 1200, height: 1000 }, reducedMotion: 'reduce' });
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    const assetRequests = [];
    const onAsset = request => { if (/\/plugins\/[^/]+\/assets\//.test(request.url())) assetRequests.push(request.url()); };
    page.on('request', onAsset);
    await page.goto(base + '/?p=1');
    assert.equal(await page.locator('[data-rps-ui]').count(), 0, 'The ordinary post must not contain a suite widget');
    assert.deepEqual(assetRequests, [], 'Ordinary host pages must not load suite assets or checkout nonces');
    page.off('request', onAsset);
    await page.route('**/wp-json/plugin/v1/**', route => {
      if (route.request().url().includes('animal-image')) return route.fulfill({ contentType: 'image/svg+xml', body: '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="600"><rect width="600" height="600" fill="#ded5e9"/></svg>' });
      return route.fulfill({ json: [{ ANIMALID: 42, ANIMALNAME: 'Mittens', SPECIESNAME: 'Cat', ANIMALAGE: '2 years', SEXNAME: 'Female', BREEDNAME: 'Domestic shorthair', MOVEMENTDATE: '2026-10-01', ANIMALCOMMENTS: 'A gentle companion.' }] });
    });
    for (const [url, trigger, modal] of [
      ['/adopt/', '.asm-card-open', '#asm-modal'], ['/stories/', '.asm-card[data-animalid]', '#asm-plugin-modal'],
    ]) {
      await page.goto(base + url);
      await page.locator(trigger).first().waitFor();
      await page.evaluate(() => {
        const style = document.createElement('style');
        style.id = 'host-probe-style';
        style.textContent = '#host-probe{padding:13px;font-family:serif;color:rgb(31,45,61)}#host-probe .grid{display:block}#host-probe .flex{display:inline}#host-probe .hidden{display:block}#host-probe [hidden]{display:block}#host-probe .relative{position:static}#host-probe button{border:3px solid rgb(15,30,45);padding:7px;font:17px serif;background:rgb(220,230,240)}#host-probe .plugin-card-name{font-size:19px}#host-probe .asm-card{border-radius:3px}#host-probe h2{text-align:left;font-size:23px}';
        document.head.prepend(style);
        const probe = document.createElement('section'); probe.id = 'host-probe';
        probe.innerHTML = '<h2 class="text-center text-lg font-bold">Host heading</h2><button class="border rounded-2xl bg-white">Host button</button><div class="grid gap-5">Host grid</div><span class="flex">Host flex</span><div class="hidden">Host visibility</div><div class="relative">Host positioning</div><span class="plugin-card-name">Host card name</span><div class="asm-card">Host card radius</div><div hidden>Host hidden override</div>';
        document.body.appendChild(probe);
        window.rpsProbeSheets = [...document.styleSheets].filter(sheet => {
          const node = sheet.ownerNode;
          return node && node.id !== 'host-probe-style' && (/\/plugins\/[^/]+\/assets\//.test(node.href || '') || /asm-adoptables-widget|asm-successstories-widget|plugin_stats_/.test(node.textContent || ''));
        });
        window.rpsProbeSnapshot = () => [...document.querySelectorAll('#host-probe,#host-probe *')].map(el => {
          const s = getComputedStyle(el);
          return ['display','position','color','fontSize','fontFamily','textAlign','padding','borderWidth','borderRadius','gap','boxSizing','margin','lineHeight','overflow'].map(p => s[p]).concat(s.getPropertyValue('--tw-ring-offset-width'));
        });
      });
      const active = await page.evaluate(() => window.rpsProbeSnapshot());
      const disabled = await page.evaluate(() => {
        window.rpsProbeSheets.forEach(sheet => { sheet.disabled = true; });
        return window.rpsProbeSnapshot();
      });
      assert.deepEqual(active, disabled, url + ': suite styles must not alter unowned theme elements');
      await page.evaluate(() => window.rpsProbeSheets.forEach(sheet => { sheet.disabled = false; }));
      await page.evaluate(() => {
        document.documentElement.style.setProperty('overflow', 'scroll', 'important');
        document.body.style.setProperty('overflow', 'clip', 'important');
        document.body.style.setProperty('position', 'relative');
        document.body.style.setProperty('top', '3px');
        document.body.style.setProperty('left', '2px');
        document.body.style.setProperty('right', '1px');
        document.body.style.setProperty('width', '93%');
        window.rpsScrollSnapshot = () => [document.documentElement,document.body].map(el => ['overflow','position','top','left','right','width'].map(p => [el.style.getPropertyValue(p), el.style.getPropertyPriority(p)]));
      });
      const styles = await page.evaluate(() => window.rpsScrollSnapshot());
      await page.locator(trigger).first().click();
      await page.waitForFunction(selector => document.querySelector(selector)?.getAttribute('aria-hidden') === 'false', modal);
      assert.equal(await page.locator(modal).evaluate(el => getComputedStyle(el).position), 'fixed', 'Detached modal root retains its scoped fixed utility');
      await page.keyboard.press('Escape');
      await page.waitForFunction(selector => document.querySelector(selector)?.getAttribute('aria-hidden') === 'true', modal);
      assert.deepEqual(await page.evaluate(() => window.rpsScrollSnapshot()), styles, url + ': closing the dialog restores host inline styles and !important priorities');
      await page.evaluate(() => {
        const suite = window.RescuePluginSuite;
        suite.lockScroll('isolation-a'); suite.lockScroll('isolation-b', true);
        suite.unlockScroll('isolation-a');
        if (document.body.style.overflow !== 'hidden') throw new Error('An open suite dialog lost its scroll lock');
        suite.unlockScroll('isolation-b');
      });
      assert.deepEqual(await page.evaluate(() => window.rpsScrollSnapshot()), styles, 'Nested scroll locks restore host styles only after the last owner closes');
      console.log('PASS ' + url + ': host stylesheet isolation, detached modal styling and scroll restoration');
    }
    assert.deepEqual(errors, [], 'Browser errors');
    console.log('PASS ordinary host page: no suite styles/scripts/checkout nonce configuration');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
