// Requires a local page containing [rescue_shop] and a published e-card product.
// No checkout, payment, upload or email requests are made.
const assert = require('node:assert/strict');
const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
  const page = await browser.newPage({ reducedMotion: 'reduce' });
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  try {
    await page.goto(process.env.SHOP_TEST_URL || 'http://127.0.0.1:8086/shop-accessibility-review/', { waitUntil: 'networkidle' });
    const root = page.locator('.ssshop').first();
    await root.locator('.ssshop-card').first().waitFor();
    await page.evaluate(() => {
      const unrelated = document.createElement('div');
      unrelated.className = 'canvas-image';
      unrelated.id = 'unrelated-shop-test';
      document.body.appendChild(unrelated);
    });
    assert.equal(await page.locator('#unrelated-shop-test').evaluate(element => getComputedStyle(element).position), 'static', 'Shop styles must not affect another component using canvas-image');
    await root.locator('.ssshop-card h3').first().click();
    assert.equal(await root.locator('.ssshop-format-filter [data-shop-format="all"]').getAttribute('aria-pressed'), 'true', 'Product clicks must not change the format filter');
    // A card-title click may already have opened the editor.
    if (!(await page.locator('[data-ssshop-modal]:visible').count())) await root.locator('.ssshop-personalise').first().click();
    const modal = page.locator('[data-ssshop-modal]:visible');
    await modal.locator('[data-add-text]').click();
    let block = modal.locator('[data-preview-text-block]').first();
    await block.focus();
    await page.keyboard.press('ArrowRight');
    block = modal.locator('[data-preview-text-block]').first();
    assert.equal(await block.evaluate(element => element.style.left), '51%');
    assert(await block.evaluate(element => document.activeElement === element), 'Keyboard movement must preserve focus');
    await page.keyboard.press('Shift+ArrowRight');
    block = modal.locator('[data-preview-text-block]').first();
    assert.equal(await block.evaluate(element => element.style.width), '56%');
    await page.keyboard.press('Enter');
    assert.match(await block.getAttribute('aria-label'), /arrow keys move/);
    await modal.locator('[data-text-bold]').click();
    assert.equal(await modal.locator('[data-text-bold]').getAttribute('aria-pressed'), 'true');
    await page.addScriptTag({ path: require.resolve('axe-core/axe.min.js') });
    for (const width of [1280, 390]) {
      await page.setViewportSize({ width, height: 900 });
      const violations = await page.evaluate(async () => {
        const result = await axe.run(document.querySelector('[data-ssshop-modal]:not([hidden])'), { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa'] } });
        return result.violations.map(item => `${item.id}: ${item.nodes.map(node => node.target.join(' ')).join(', ')}`);
      });
      assert.deepEqual(violations, [], `Shop modal accessibility at ${width}px`);
    }
    if (process.env.SHOP_GROUP_TEST_URL) {
      await page.goto(process.env.SHOP_GROUP_TEST_URL, { waitUntil: 'networkidle' });
      await page.locator('#addText').click();
      await page.locator('.obj.mine').last().focus();
      await page.keyboard.press('ArrowRight');
      assert.equal(await page.locator('.obj.mine').last().evaluate(element => element.style.left), '51%');
      await page.keyboard.press('Shift+ArrowRight');
      assert.equal(await page.locator('.obj.mine').last().evaluate(element => element.style.width), '56%');
      assert(await page.locator('.obj.mine').last().evaluate(element => document.activeElement === element));
      await page.addScriptTag({ path: require.resolve('axe-core/axe.min.js') });
      assert.deepEqual(await page.evaluate(async () => (await axe.run({ runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa'] } })).violations.map(item => item.id)), [], 'Group editor accessibility');
    }
    if (process.env.SHOP_CARD_TEST_URL) {
      await page.goto(process.env.SHOP_CARD_TEST_URL, { waitUntil: 'networkidle' });
      await page.locator('#reveal').click();
      assert.equal(await page.locator('.particle').count(), 0, 'Reduced motion suppresses recipient celebration particles');
      await page.addScriptTag({ path: require.resolve('axe-core/axe.min.js') });
      assert.deepEqual(await page.evaluate(async () => (await axe.run({ runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa'] } })).violations.map(item => item.id)), [], 'Recipient card accessibility');
    }
    assert.deepEqual(errors, []);
    console.log('Shop keyboard selection/movement/resize, filter isolation, CSS isolation and desktop/mobile axe checks passed.');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
