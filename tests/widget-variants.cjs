/* Run only against a disposable fixture: the test saves and restores widget settings. */
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const base = process.env.RPS_BASE_URL || 'http://127.0.0.1:8086';
const axePath = require.resolve('axe-core/axe.min.js');
const animals = Array.from({ length: 18 }, (_, index) => ({
  ANIMALID: 42 + index, ANIMALNAME: ['Mittens', 'Willow', 'Oliver'][index % 3] + (index > 2 ? ' ' + index : ''),
  ANIMALAGE: '2 years', SEXNAME: 'Female', SPECIESID: 2, SPECIESNAME: 'Cat',
  BREEDNAME: 'Domestic shorthair', MOVEMENTDATE: '2026-10-01',
  ANIMALCOMMENTS: 'A gentle companion who loves sunny windows and quiet company. She is ready to find a welcoming home and a family to share her next chapter with.',
}));
const svg = '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="600"><rect width="600" height="600" fill="#ddd7e5"/><circle cx="300" cy="300" r="140" fill="#9b8dac"/></svg>';
const modules = {
  adoptables: { path: '/adopt/', root: '#asm-adoptables-widget', grid: '#asm-grid' },
  adopted: { path: '/stories/', root: '#asm-successstories-widget', grid: '#asm-plugin-grid' },
  stats: { path: '/impact/', root: '.plugin-stats-ui-wrap' },
};

(async () => {
  assert(process.env.RPS_ADMIN_USER && process.env.RPS_ADMIN_PASSWORD, 'Supply fixture administrator credentials');
  const browser = await chromium.launch({ headless: true, ...(process.env.RPS_CHROMIUM ? { executablePath: process.env.RPS_CHROMIUM } : {}) });
  const context = await browser.newContext({ viewport: { width: 1440, height: 1100 }, reducedMotion: 'reduce' });
  const admin = await context.newPage(), page = await context.newPage();
  const original = {}, errors = [];
  page.on('pageerror', error => errors.push(error.message));
  const field = (module, key) => admin.locator(`[name="suite[${module}][${key}]"]`).last();
  const keys = module => module === 'stats' ? ['layout_preset'] : ['layout_preset', 'card_design', 'visitor_view_switch'];
  async function openSettings(module) {
    await admin.goto(base + '/wp-admin/admin.php?page=plugin-ui-suite&tab=' + module + '&subtab=design');
    await field(module, 'layout_preset').waitFor();
  }
  async function save(module, values) {
    await openSettings(module);
    for (const [key, value] of Object.entries(values)) {
      if (key === 'visitor_view_switch') await field(module, key).setChecked(value);
      else await field(module, key).selectOption(value);
    }
    const form = field(module, 'layout_preset').locator('xpath=ancestor::form');
    await Promise.all([admin.waitForURL(/updated=true/), form.locator('input[type=submit]').click()]);
    for (const [key, value] of Object.entries(values)) {
      assert.equal(key === 'visitor_view_switch' ? await field(module, key).isChecked() : await field(module, key).inputValue(), value, `${module}.${key} persists`);
    }
  }
  async function openWidget(module) {
    await page.goto(base + modules[module].path);
    const root = page.locator(modules[module].root);
    await root.waitFor();
    if (module !== 'stats') await root.locator('.rps-animal-card[data-animalid]').first().waitFor();
    else await root.locator('.stat-number').first().waitFor();
    assert(await page.locator('link[href*="widget-layouts.css"]').count(), 'Preset stylesheet is loaded');
    return root;
  }
  async function geometry(root) {
    return root.locator('.rps-animal-card[data-animalid]').first().evaluate(card => {
      const bounds = el => { const b = el.getBoundingClientRect(); return { x: b.x, y: b.y, width: b.width, height: b.height }; };
      return { card: bounds(card), media: bounds(card.querySelector('.rps-card-media')), photo: bounds(card.querySelector('.rps-card-media img')), details: bounds(card.querySelector('.rps-card-details')), padding: getComputedStyle(card.querySelector('.rps-card-details')).paddingTop };
    });
  }
  async function checkAxe(root, label) {
    await page.addScriptTag({ path: axePath });
    const violations = await root.evaluate(async el => (await axe.run(el, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa'] } })).violations.map(v => ({ id: v.id, nodes: v.nodes.map(n => n.html) })));
    assert.deepEqual(violations, [], label + ' accessibility');
    assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), label + ' horizontal overflow');
  }
  try {
    await admin.goto(base + '/wp-login.php');
    await admin.locator('#user_login').fill(process.env.RPS_ADMIN_USER);
    await admin.locator('#user_pass').fill(process.env.RPS_ADMIN_PASSWORD);
    await admin.locator('#wp-submit').click();
    for (const module of Object.keys(modules)) {
      await openSettings(module);
      original[module] = {};
      for (const key of keys(module)) original[module][key] = key === 'visitor_view_switch' ? await field(module, key).isChecked() : await field(module, key).inputValue();
    }
    await page.route('**/wp-json/plugin/v1/**', route => {
      const url = route.request().url();
      if (url.includes('animal-image')) return route.fulfill({ contentType: 'image/svg+xml', body: svg });
      const json = url.includes('/report') ? [{ year: 2026, cats_brought_in: 120, cats_adopted: 96, cats_vaccinated: 110, cats_neutered: 108, cats_chipped: 105 }] : url.includes('in-care-count') ? { count: 24 } : animals;
      return route.fulfill({ json });
    });
    for (const module of ['adoptables', 'adopted']) {
      await page.setViewportSize({ width: 1440, height: 1100 });
      await save(module, { layout_preset: 'grid', card_design: 'classic', visitor_view_switch: false });
      let root = await openWidget(module);
      const classic = await geometry(root);
      assert.equal(await root.getAttribute('data-rps-layout'), 'grid');
      assert.equal(await root.locator('.rps-view-switch').count(), 0, 'Visitor view controls default to disabled');
      await save(module, { card_design: 'compact' });
      root = await openWidget(module);
      const compact = await geometry(root);
      assert(compact.card.height <= classic.card.height, `${module}: compact cards remain no taller than classic (${compact.card.height} vs ${classic.card.height})`);
      assert(Math.abs(compact.photo.width - compact.photo.height) <= 1.5, module + ': compact keeps square photos');
      assert(parseFloat(compact.padding) <= parseFloat(classic.padding), module + ': compact spacing does not exceed classic');
      await checkAxe(root, module + ' compact');
      await save(module, { card_design: 'thumbnail' });
      root = await openWidget(module);
      const thumbnail = await geometry(root);
      assert(thumbnail.details.y >= thumbnail.media.y + thumbnail.media.height - 2, module + ' photo stays above details');
      assert(thumbnail.media.width >= thumbnail.card.width * .9, module + ' photographs remain the focal point');
      assert(Math.abs(thumbnail.photo.width - thumbnail.photo.height) <= 1.5, module + ': thumbnails stay square');
      await save(module, { layout_preset: 'blog', card_design: 'classic' });
      root = await openWidget(module);
      assert.equal(await root.getAttribute('data-rps-layout'), 'blog');
      assert.notEqual(await root.locator('.rps-card-excerpt').first().evaluate(el => getComputedStyle(el).display), 'none', 'Blog shows story excerpts');
      assert(await root.locator('.rps-card-media').first().evaluate(el => el.getBoundingClientRect().width <= 420), 'Blog photos remain bounded');
      await checkAxe(root, module + ' blog');
      await save(module, { layout_preset: 'list', visitor_view_switch: true });
      root = await openWidget(module);
      assert.equal(await root.getAttribute('data-rps-view'), 'list', 'Saved list layout is the initial visitor view');
      const group = root.getByRole('group', { name: 'Animal display view' });
      const gridButton = group.getByRole('button', { name: 'Grid', exact: true });
      const listButton = group.getByRole('button', { name: 'List', exact: true });
      await expectView('list');
      const initialIds = await root.locator('.rps-animal-card[data-animalid]').evaluateAll(nodes => nodes.map(el => el.dataset.animalid));
      const initialPager = await root.locator('[id*="page-label"]').allTextContents();
      await gridButton.click();
      await expectView('grid');
      await gridButton.press('ArrowRight');
      assert(await listButton.evaluate(el => document.activeElement === el), 'Arrow navigation moves focus');
      await expectView('list');
      assert.deepEqual(await root.locator('.rps-animal-card[data-animalid]').evaluateAll(nodes => nodes.map(el => el.dataset.animalid)), initialIds, 'View change preserves results and order');
      assert.deepEqual(await root.locator('[id*="page-label"]').allTextContents(), initialPager, 'View change preserves pagination');
      await gridButton.click();
      await page.reload();
      root = page.locator(modules[module].root);
      await root.locator('.rps-animal-card[data-animalid]').first().waitFor();
      assert.equal(await root.getAttribute('data-rps-view'), 'grid', 'Visitor choice persists for the session');
      await page.setViewportSize({ width: 390, height: 1000 });
      await root.getByRole('button', { name: 'List', exact: true }).click();
      await checkAxe(root, module + ' mobile list and view controls');
      await save(module, { visitor_view_switch: false });
      root = await openWidget(module);
      assert.equal(await root.locator('.rps-view-switch').count(), 0);
      assert.equal(await root.getAttribute('data-rps-view'), 'list', 'Disabled visitor option uses the administrator layout');
      console.log(`PASS ${module}: admin layouts, compact/thumbnail cards, responsive accessibility and visitor view persistence`);
      async function expectView(view) {
        assert.equal(await root.getAttribute('data-rps-view'), view);
        assert.equal(await gridButton.getAttribute('aria-pressed'), view === 'grid' ? 'true' : 'false');
        assert.equal(await listButton.getAttribute('aria-pressed'), view === 'list' ? 'true' : 'false');
        if (view === 'list') {
          const row = await geometry(root);
          assert(row.details.x >= row.media.x + row.media.width - 2, module + ' list arranges image beside details');
        }
      }
    }
    await page.setViewportSize({ width: 1440, height: 1100 });
    for (const preset of ['tiles', 'compact', 'band']) {
      await save('stats', { layout_preset: preset });
      const root = await openWidget('stats');
      assert.equal(await root.getAttribute('data-rps-layout'), preset);
      if (preset === 'band') {
        assert.equal(await root.locator('[data-plugin-grid]').evaluate(el => getComputedStyle(el).columnGap), '0px');
        assert.equal(await root.locator('.counter-card').first().evaluate(el => getComputedStyle(el, '::after').width), '1px', 'Band has dividers');
      }
      await checkAxe(root, 'Impact ' + preset);
    }
    assert.deepEqual(errors, [], 'Browser JavaScript errors');
    console.log('PASS impact: all three administrator presets and accessibility');
  } finally {
    // Each save touches only the same visible controls; unrelated settings are retained.
    try { for (const [module, values] of Object.entries(original)) await save(module, values); }
    finally { await browser.close(); }
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
