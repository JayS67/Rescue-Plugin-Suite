/* Disposable WordPress fixture only: saves and restores the statistics settings. */
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const axePath = require.resolve('axe-core/axe.min.js');
const base = process.env.RPS_BASE_URL || 'http://127.0.0.1:8086';
(async () => {
  assert(process.env.RPS_ADMIN_USER && process.env.RPS_ADMIN_PASSWORD, 'Supply fixture administrator credentials');
  const browser = await chromium.launch({ headless: true, executablePath: process.env.RPS_CHROMIUM || undefined, args: ['--no-sandbox'] });
  const context = await browser.newContext({ viewport: { width: 1440, height: 1100 }, reducedMotion: 'reduce' });
  const admin = await context.newPage(), page = await context.newPage();
  const original = {}, errors = [];
  page.on('pageerror', error => errors.push(error.message));
  const field = key => admin.locator(`[name="suite[stats][${key}]"]`).last();
  const values = { typography: {}, responsive: { card_padding_mobile: '10' }, design: {} };
  const sizes = { mobile: { heading: 24, subheading: 13, paragraph: 11, number: 22, icon: 20 }, tablet: { heading: 35, subheading: 17, paragraph: 15, number: 32, icon: 30 }, desktop: { heading: 44, subheading: 21, paragraph: 18, number: 46, icon: 44 } };
  for (const [device, config] of Object.entries(sizes)) {
    for (const type of ['heading', 'subheading', 'paragraph', 'number']) values.typography[`fs_${type}_${device}`] = String(config[type]);
    values.typography[`icon_size_${device}`] = String(config.icon);
  }
  async function settings(sub) { await admin.goto(`${base}/wp-admin/admin.php?page=plugin-ui-suite&tab=stats&subtab=${sub}`); }
  async function save(sub, changes) {
    await settings(sub);
    for (const [key, value] of Object.entries(changes)) {
      if (key === 'layout_preset') await field(key).selectOption(value);
      else await field(key).fill(value);
    }
    const form = admin.locator('form').filter({ has: field(Object.keys(changes)[0]) });
    await Promise.all([admin.waitForURL(/[?&](?:updated=true|saved=1)(?:&|$)/), form.locator('input[type=submit]').click()]);
    for (const [key, value] of Object.entries(changes)) assert.equal(await field(key).inputValue(), value, `${key} persists`);
  }
  async function checkSizing(root, expectedDevice) {
    await page.waitForFunction(({ selector, device }) => document.querySelector(selector)?.dataset.rpsStatsDevice === device, { selector: '.plugin-stats-ui-wrap', device: expectedDevice });
    const actual = await root.evaluate(r => {
      const css = selector => getComputedStyle(r.querySelector(selector));
      const card = r.querySelector('.rps-impact-card');
      return { heading: parseFloat(css('.plugin-heading').fontSize), subheading: parseFloat(css('.stat-heading').fontSize), paragraph: parseFloat(css('.stat-caption').fontSize), number: parseFloat(css('.stat-number').fontSize), icon: r.querySelector('.icon-float').getBoundingClientRect().width, padding: parseFloat(getComputedStyle(card).paddingTop), overflow: card.scrollWidth > card.clientWidth + 1 };
    });
    const device = { m: 'mobile', t: 'tablet', d: 'desktop' }[expectedDevice];
    for (const key of ['heading', 'subheading', 'paragraph', 'number', 'icon']) assert.equal(actual[key], sizes[device][key], `${device} ${key} setting applies`);
    if (expectedDevice === 'm') assert.equal(actual.padding, 10, 'The saved mobile padding applies');
    assert(!actual.overflow, 'Statistics card content stays within its card');
  }
  try {
    await admin.goto(base + '/wp-login.php');
    await admin.locator('#user_login').fill(process.env.RPS_ADMIN_USER);
    await admin.locator('#user_pass').fill(process.env.RPS_ADMIN_PASSWORD);
    await Promise.all([admin.waitForURL(/wp-admin/), admin.locator('#wp-submit').click()]);
    for (const [sub, changes] of Object.entries(values)) {
      await settings(sub); original[sub] = {};
      for (const key of Object.keys(changes)) original[sub][key] = await field(key).inputValue();
    }
    await settings('design'); original.design.layout_preset = await field('layout_preset').inputValue();
    await save('typography', values.typography);
    await save('responsive', values.responsive);
    await page.route('**/wp-json/plugin/v1/**', async route => {
      const url = new URL(route.request().url());
      const year = Number(url.searchParams.get('year')) || new Date().getFullYear();
      return route.fulfill({ json: url.pathname.endsWith('/in-care-count') ? { count: 15 } : [{ year, cats_brought_in: 43, cats_adopted: 56, cats_vaccinated: 41, cats_neutered: 37, cats_chipped: 38 }] });
    });
    for (const layout of ['tiles', 'compact', 'band']) {
      await save('design', { layout_preset: layout });
      await page.setViewportSize({ width: 375, height: 1000 });
      await page.goto(base + '/impact/');
      const root = page.locator('.plugin-stats-ui-wrap').first();
      await root.locator('[id$="_count_brought"]').getByText('43', { exact: true }).waitFor();
      assert(await page.locator('link[href*="statistics.css"]').count(), 'Statistics stylesheet is loaded');
      await checkSizing(root, 'm');
      assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${layout} mobile does not overflow`);
      // Exercise each actual embed width even when the host theme has a narrow article column.
      await page.setViewportSize({ width: 1600, height: 1100 });
      for (const [width, device] of [[760, 't'], [1120, 'd'], [360, 'm']]) {
        await root.evaluate((r, width) => { r.style.setProperty('width', width + 'px', 'important'); r.style.setProperty('max-width', 'none', 'important'); }, width);
        await checkSizing(root, device);
      }
      await page.addScriptTag({ path: axePath });
      const violations = await root.evaluate(async r => (await axe.run(r, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa'] } })).violations.map(v => v.id));
      assert.deepEqual(violations, [], `${layout} automated accessibility checks`);
    }
    assert.deepEqual(errors, [], 'Statistics layouts produce no browser errors');
    console.log('Statistics settings passed: values save and apply for totals, icons, headings, captions and mobile padding across all layouts and embed widths.');
  } finally {
    try {
      for (const [sub, saved] of Object.entries(original)) if (Object.keys(saved).length) await save(sub, saved);
    } catch (error) {
      console.error('Statistics fixture settings could not be fully restored:', error);
      process.exitCode = 1;
    } finally { await browser.close(); }
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
