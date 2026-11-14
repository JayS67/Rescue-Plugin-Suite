/* Disposable fixture only. RPS_ADMIN_USER/PASSWORD are supplied at runtime, never stored. */
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const base = process.env.RPS_BASE_URL || 'http://127.0.0.1:8086';
const keys = ['widget_transparent_background','widget_show_titles','widget_show_subtitles','widget_show_footers'];
(async () => {
  assert(process.env.RPS_ADMIN_USER && process.env.RPS_ADMIN_PASSWORD, 'Supply fixture admin credentials');
  const browser = await chromium.launch({ headless: true, ...(process.env.RPS_CHROMIUM ? { executablePath: process.env.RPS_CHROMIUM } : {}) });
  const page = await browser.newPage({ viewport: { width: 1200, height: 950 }, reducedMotion: 'reduce' });
  const url = base + '/wp-admin/options-general.php?page=plugin-ui-suite&tab=global';
  let original;
  async function setFlags(values) {
    await page.goto(url);
    for (const key of keys) await page.locator(`input[type=checkbox][name="suite[global][${key}]"]`).setChecked(values[key]);
    const form = page.locator('form').filter({ has: page.locator('input[type=checkbox][name="suite[global][widget_transparent_background]"]') });
    await Promise.all([page.waitForURL(/updated=/), form.locator('input[type=submit]').click()]);
    for (const key of keys) assert.equal(await page.locator(`input[type=checkbox][name="suite[global][${key}]"]`).isChecked(), values[key], key + ' persists');
  }
  try {
    await page.goto(base + '/wp-login.php');
    await page.locator('#user_login').fill(process.env.RPS_ADMIN_USER);
    await page.locator('#user_pass').fill(process.env.RPS_ADMIN_PASSWORD);
    await page.locator('#wp-submit').click();
    await page.goto(url);
    await page.locator('.rps-admin').waitFor();
    original = Object.fromEntries(await Promise.all(keys.map(async key => [key, await page.locator(`input[type=checkbox][name="suite[global][${key}]"]`).isChecked()])));
    await setFlags(Object.fromEntries(keys.map(key => [key, key === 'widget_transparent_background'])));
    await page.route('**/wp-json/plugin/v1/**', async route => {
      if (route.request().url().includes('animal-image')) return route.fulfill({ contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400"><rect width="400" height="400" fill="#ddd"/></svg>' });
      return route.fulfill({ json: [{ ANIMALID:42, ANIMALNAME:'Mittens', SPECIESID:2, SPECIESNAME:'Cat', MOVEMENTDATE:'2026-10-01' }] });
    });
    for (const path of ['/adopt/', '/stories/', '/impact/', '/widget-review/']) {
      await page.goto(base + path);
      const roots = page.locator('[data-rps-transparent]');
      assert(await roots.count() > 0, path + ' marked roots');
      for (const root of await roots.all()) {
        assert.equal(await root.getAttribute('data-rps-transparent'), 'true');
        const style = await root.evaluate(el => ({ background:getComputedStyle(el).backgroundColor, image:getComputedStyle(el).backgroundImage }));
        assert.equal(style.background, 'rgba(0, 0, 0, 0)', path + ' transparent outer surface');
        assert.equal(style.image, 'none');
        for (const title of await root.locator('.rps-widget-title').all()) {
          const style = await title.evaluate(el => ({position:getComputedStyle(el).position,width:getComputedStyle(el).width}));
          assert.equal(style.position, 'absolute', 'Hidden visual titles remain accessible');
          assert.equal(style.width, '1px');
        }
        for (const text of await root.locator('.rps-widget-subtitle,.rps-widget-footer').all()) assert.equal(await text.evaluate(el=>getComputedStyle(el).display), 'none');
      }
      for (const dialog of await page.locator('[role=dialog]').all()) assert.equal(await dialog.getAttribute('data-rps-transparent'), null, 'Dialogs never inherit transparent surfaces');
      if (path === '/adopt/' || path === '/stories/') assert(await page.locator('.asm-paw-bg').count() > 0, 'Paw markup is retained');
      if (path === '/impact/') {
        assert(await page.locator('.paw-bg').count() > 0);
        const backdrop = page.locator('.rps-widget-backdrop');
        assert.equal(await backdrop.evaluate(el => getComputedStyle(el).backgroundColor), 'rgba(0, 0, 0, 0)');
      }
      console.log('PASS transparent/text visibility: ' + path);
    }
  } finally {
    if (original) await setFlags(original);
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode=1; });
