/* Run against the disposable WordPress fixture in docs/design-and-performance.md. */
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const base = process.env.RPS_BASE_URL || 'http://127.0.0.1:8086';
const animals = ['Mittens', 'Willow', 'Oliver'].map((name, i) => ({
  ANIMALID: 42 + i, ANIMALNAME: name, ANIMALAGE: '2 years', SEXNAME: 'Female',
  SPECIESID: 2, SPECIESNAME: 'Cat', BREEDNAME: 'Domestic shorthair', MOVEMENTDATE: '2026-10-01',
  ANIMALCOMMENTS: 'A friendly rescue companion looking for a loving home. '.repeat(45),
}));
const svg = '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="600"><rect width="600" height="600" fill="#ddd4eb"/></svg>';
(async () => {
  const browser = await chromium.launch({ headless: true, ...(process.env.RPS_CHROMIUM ? { executablePath: process.env.RPS_CHROMIUM } : {}) });
  const errors = [];
  try {
    const page = await browser.newPage({ reducedMotion: 'reduce' });
    page.on('pageerror', error => errors.push(error.message));
    // Exercise a configured application form without changing shared fixture settings.
    await page.route(base + '/adopt/', async route => {
      const response = await route.fetch();
      let html = await response.text();
      html = html.replace(/applyFormEnabled: (?:true|false)/, 'applyFormEnabled: true')
        .replace(/applyFormScriptUrl: "(?:\\.|[^"\\])*"/, 'applyFormScriptUrl: "https://service.sheltermanager.com/asmservice?method=online_form&formid=1"');
      await route.fulfill({ response, body:html });
    });
    await page.route('https://service.sheltermanager.com/asmservice**', route => route.fulfill({
      contentType:'text/javascript',
      body: 'document.getElementById("asm3-onlineform").innerHTML = \'<form><label for="fixture-name">Your name</label><input id="fixture-name" autocomplete="name"><p style="height:1400px">Application form preview</p><button type="button">Preview only</button></form>\';',
    }));
    await page.route('**/wp-json/plugin/v1/**', route => route.fulfill(route.request().url().includes('animal-image') ? { contentType: 'image/svg+xml', body: svg } : { json: animals }));
    for (const width of [390, 1440]) {
      await page.setViewportSize({ width, height: 900 });
      for (const [url, open, prefix] of [['/adopt/', '.asm-card-open', 'asm-modal'], ['/stories/', '.asm-card[data-animalid]', 'asm-plugin-modal']]) {
        assert.equal((await page.goto(base + url)).status(), 200);
        const opener = page.locator(open).first();
        await opener.click();
        const modal = page.locator('#' + prefix);
        await page.waitForFunction(id => document.getElementById(id)?.getAttribute('aria-hidden') === 'false', prefix);
        const nav = modal.locator('.rps-modal-animal-nav');
        const previous = nav.locator('button').first();
        const next = nav.locator('button').last();
        assert.equal(await previous.isVisible(), false, 'No previous animal before the first result');
        assert.equal(await next.isVisible(), true);
        const geometry = async () => page.evaluate(id => {
          const panel = document.getElementById(id + '-panel');
          const nav = panel.querySelector('.rps-modal-animal-nav');
          const rect = el => { const r = el.getBoundingClientRect(); return { left:r.left, right:r.right, top:r.top, bottom:r.bottom, height:r.height }; };
          return { panel:rect(panel), nav:rect(nav), scroll:rect(document.getElementById(id + '-scroll')), previous:rect(nav.firstElementChild), next:rect(nav.lastElementChild), direct:nav.parentElement === panel };
        }, prefix);
        let g = await geometry();
        assert(g.direct && g.nav.bottom <= g.panel.bottom + 1 && g.nav.bottom >= g.panel.bottom - 5, 'Animal controls sit inside the shared panel footer');
        assert(g.scroll.bottom <= g.nav.top + 1, 'Scrolling content cannot overlap animal navigation');
        assert(g.next.right >= g.nav.right - 20 && g.next.height >= 44, 'Next remains at the right with an accessible touch target');
        assert(g.panel.left >= 0 && g.panel.right <= width && g.panel.top >= 0 && g.panel.bottom <= 900, 'The entire modal remains within the viewport');
        await next.click();
        assert.equal(await previous.isVisible(), true);
        g = await geometry();
        assert(g.previous.left <= g.nav.left + 20 && g.previous.height >= 44 && g.next.left > g.previous.right, 'Previous stays left and Next stays right');
        const footerTop = g.nav.top;
        await page.locator('#' + prefix + '-scroll').evaluate(el => { el.scrollTop = el.scrollHeight; });
        assert(Math.abs((await geometry()).nav.top - footerTop) < 1, 'The footer stays in place while the animal details scroll');
        if (prefix === 'asm-plugin-modal' && process.env.RPS_EXPECT_ADOPTION_CTA) {
          const cta = modal.locator('#asm-plugin-adoptables-cta a');
          await cta.scrollIntoViewIfNeeded();
          assert.equal(await cta.isVisible(), true);
          assert.equal(new URL(await cta.getAttribute('href'), base).pathname, '/adopt/');
        }
        await previous.click();
        assert.equal(await previous.isVisible(), false);
        if (prefix === 'asm-modal' && await modal.locator('#asm-apply-toggle-top').isVisible()) {
          await modal.locator('#asm-apply-toggle-top').click();
          await modal.frameLocator('#asm-apply-form-mount iframe').locator('#fixture-name').waitFor();
          await modal.locator('#asm-modal-scroll').evaluate(el => { el.scrollTop = el.scrollHeight; });
          g = await geometry();
          assert(g.scroll.bottom <= g.nav.top + 1 && g.nav.bottom <= g.panel.bottom + 1, 'A long application iframe stays inside the scroll area above navigation');
          await modal.locator('#asm-apply-back-top').click();
        }
        await page.keyboard.press('Tab');
        assert(await modal.evaluate(el => el.contains(document.activeElement)), 'Keyboard focus stays inside the dialog');
        await page.keyboard.press('Escape');
        await page.waitForFunction(id => document.getElementById(id)?.getAttribute('aria-hidden') === 'true', prefix);
        assert(await opener.evaluate(el => el === document.activeElement), 'Closing restores the originating card focus');
        console.log(`PASS ${width}px ${url}: consistent navigation, scroll boundary, touch targets and keyboard dismissal`);
      }
    }
    await page.unroute(base + '/adopt/');
    const actual = await page.goto(base + '/adopt/');
    const enabled = (await actual.text()).match(/applyFormEnabled: (true|false)/);
    assert(enabled, 'The widget declares whether an application form is configured');
    await page.locator('.asm-card-open').first().click();
    assert.equal(await page.locator('#asm-apply-toggle-top').isVisible(), enabled[1] === 'true', 'Opening a modal respects the configured Apply setting');
    assert.deepEqual(errors, [], 'No modal JavaScript errors');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
