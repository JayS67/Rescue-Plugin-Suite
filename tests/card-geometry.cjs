/* Disposable WordPress browser fixture. No settings, forms, payments or live feeds are changed.
 * The matrix sets the public layout attributes that administrator settings emit;
 * tests/widget-variants.cjs separately checks saving those settings through WordPress.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { chromium } = require('playwright');
const base = process.env.RPS_BASE_URL || 'http://127.0.0.1:8086';
const output = process.env.RPS_CARD_SCREENSHOTS || path.join(os.tmpdir(), 'rps-card-geometry');
const axePath = require.resolve('axe-core/axe.min.js');
const story = 'First line: a gentle companion.\nSecond line: enjoys a sunny window.\n\nA new paragraph about a welcoming home. ' + 'More of this animal’s story is available in the modal. '.repeat(15) + 'END OF FULL STORY';
const animals = Array.from({ length: 18 }, (_, index) => ({
  ANIMALID: 42 + index,
  ANIMALNAME: ['Mittens', 'Princess Willow and her inseparable friend Oliver with the exceptionally long name', 'Bo'][index % 3],
  ANIMALAGE: index % 3 ? '12 years and 11 months' : '',
  SEXNAME: index % 3 ? 'Female' : '',
  SPECIESID: 2, SPECIESNAME: 'Cat',
  BREEDNAME: index % 3 ? 'Domestic long-haired crossbreed with an exceptionally long breed description' : '',
  MOVEMENTDATE: '2026-10-01',
  primary_reservation_status: index % 3 === 1 ? 'pending adoption' : '',
  has_active_reservation: index % 3 === 2,
  ANIMALCOMMENTS: index % 3 === 0 ? story : index % 3 === 1 ? '<p>First HTML paragraph.</p><p>Second paragraph.<br>Next line.</p>' : '',
}));
const squareImage = '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="600" viewBox="0 0 600 600"><rect width="600" height="600" fill="#ddd7e5"/><rect x="8" y="8" width="584" height="584" fill="none" stroke="#401268" stroke-width="12"/><circle cx="300" cy="300" r="140" fill="#9b8dac"/></svg>';
const modules = {
  adoptables: { path: '/adopt/', root: '#asm-adoptables-widget', modal: '#asm-modal', story: '#asm-field-desc', trigger: '.asm-card-open' },
  adopted: { path: '/stories/', root: '#asm-successstories-widget', modal: '#asm-plugin-modal', story: '#asm-plugin-modal-story', trigger: null },
};

(async () => {
  fs.mkdirSync(output, { recursive: true });
  const browser = await chromium.launch({ headless: true, ...(process.env.RPS_CHROMIUM ? { executablePath: process.env.RPS_CHROMIUM } : {}) });
  const page = await browser.newPage({ reducedMotion: 'reduce' });
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  try {
    await page.route('**/wp-json/plugin/v1/**', route => route.fulfill(route.request().url().includes('animal-image') ? { contentType: 'image/svg+xml', body: squareImage } : { json: animals }));
    for (const [module, selectors] of Object.entries(modules)) {
      if (process.env.RPS_CARD_MODULES && !process.env.RPS_CARD_MODULES.split(',').includes(module)) continue;
      for (const width of [390, 768, 1440]) {
        await page.setViewportSize({ width, height: 1100 });
        await page.goto(base + selectors.path);
        const root = page.locator(selectors.root);
        await root.locator('.rps-animal-card[data-animalid]').first().waitFor();
        await page.evaluate(() => document.fonts.ready);
        for (const layout of ['grid', 'list', 'blog']) {
          for (const design of ['classic', 'compact', 'thumbnail']) {
            await root.evaluate((element, config) => {
              element.dataset.rpsLayout = config.layout;
              element.dataset.rpsView = config.layout === 'list' ? 'list' : 'grid';
              element.dataset.rpsCardDesign = config.design;
            }, { layout, design });
            await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))));
            const label = `${module} ${layout}/${design} at ${width}px`;
            const cards = await root.locator('.rps-animal-card[data-animalid]').evaluateAll(nodes => nodes.map(card => {
              const box = element => { const b = element.getBoundingClientRect(); return { left: b.left, right: b.right, top: b.top, bottom: b.bottom, width: b.width, height: b.height }; };
              const media = card.querySelector('.rps-card-media');
              const photo = media.querySelector('img');
              const frame = media.querySelector('.asm-ad-imgwrap, .aspect-square') || photo;
              const favourite = card.querySelector('.asm-fav-toggle'); const status = card.querySelector('.rps-card-status:not(.rps-card-status-empty)');
              return { id: card.dataset.animalid, card: box(card), media: box(media), frame: box(frame), photo: box(photo), fit: getComputedStyle(photo).objectFit, name: box(card.querySelector('.rps-card-name')), favourite: favourite ? box(favourite) : null, status: status ? box(status) : null };
            }));
            assert(cards.length >= 3, label + ': multiple varied cards rendered');
            const heights = cards.map(item => item.card.height);
            assert(Math.max(...heights) - Math.min(...heights) <= 1.5, label + ': same card height across all visible rows: ' + heights.join(', '));
            for (const item of cards) {
              assert(Math.abs(item.frame.width - item.frame.height) <= 1.5, `${label}: photo frame ${item.id} is square (${item.frame.width}x${item.frame.height})`);
              assert(Math.abs(item.photo.width - item.photo.height) <= 1.5, `${label}: image ${item.id} is square`);
              assert(item.media.width > 0 && item.media.height > 0, label + ': visible image');
              assert.equal(item.fit, 'contain', label + ': complete photograph is visible without cropping');
              if (item.favourite && (design !== 'classic' || layout !== 'grid')) assert(item.photo.bottom <= item.favourite.top - 3, label + ': favourites do not cover the photograph');
              if (item.status) assert(item.status.left >= item.media.right - 1.5 || item.status.top >= item.media.bottom - 1.5, label + ': reservation labels do not cover the photograph');
              assert(item.media.left >= item.card.left - 1.5 && item.media.right <= item.card.right + 1.5, label + ': media stays inside card');
              assert(item.media.bottom <= item.card.bottom + 1.5, label + ': media is not clipped by short card');
              assert(item.name.right <= item.card.right + 1.5, label + ': long name stays inside card');
            }
            assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), label + ': no horizontal page overflow');
            if (layout === 'blog') {
              assert(Math.max(...cards.map(item => item.media.width)) <= Math.min(width, 340), label + ': blog photo is bounded instead of full-width hero');
              assert(Math.max(...heights) <= 550, label + ': blog cards stay compact');
              const excerpt = root.locator('.rps-animal-card[data-animalid="42"] .rps-card-excerpt');
              const data = await excerpt.evaluate(element => ({ text: element.textContent, whitespace: getComputedStyle(element).whiteSpace, display: getComputedStyle(element).display }));
              assert.match(data.text, /First line:[^\n]*\nSecond line:/, label + ': literal line break is preserved');
              assert.match(data.text, /\n\nA new paragraph/, label + ': paragraph separation is preserved');
              assert(['pre-line', 'pre-wrap', 'break-spaces'].includes(data.whitespace), label + ': line breaks render visually');
              assert.notEqual(data.display, 'none', label + ': description preview is visible');
              const htmlExcerpt = await root.locator('.rps-animal-card[data-animalid="43"] .rps-card-excerpt').textContent();
              assert.match(htmlExcerpt, /First HTML paragraph\.\n+Second paragraph\.\nNext line\./, label + ': HTML paragraphs and BR become readable line breaks');
              assert(!await root.locator('.rps-card-excerpt script, .rps-card-excerpt img').count(), label + ': feed HTML does not become executable markup');
            }
            if (design === 'compact' && layout === 'grid' || layout === 'blog' && design === 'classic') {
              await root.screenshot({ path: path.join(output, `${module}-${layout}-${design}-${width}.png`) });
            }
          }
        }
        // Accessibility and full-story access use the compact editorial view.
        await root.evaluate(element => { element.dataset.rpsLayout = 'blog'; element.dataset.rpsView = 'grid'; element.dataset.rpsCardDesign = 'compact'; });
        await page.addScriptTag({ path: axePath });
        const violations = await root.evaluate(async element => (await axe.run(element, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa'] } })).violations.map(item => ({ id: item.id, nodes: item.nodes.map(node => node.html) })));
        assert.deepEqual(violations, [], `${module} blog accessibility at ${width}px`);
        const first = root.locator('.rps-animal-card[data-animalid="42"]');
        await (selectors.trigger ? first.locator(selectors.trigger) : first).click();
        await page.locator(selectors.modal).waitFor({ state: 'visible' });
        assert.match(await page.locator(selectors.story).textContent(), /END OF FULL STORY/, 'Preview truncation must not shorten the full modal story');
        await page.keyboard.press('Escape');
        console.log(`PASS ${module} ${width}px: nine card/layout combinations, equal heights, square photos, compact blog, paragraph previews and accessible full story`);
      }
    }
    for (const [module, selectors] of Object.entries(modules)) {
      await page.setViewportSize({ width: 390, height: 1000 });
      await page.goto(base + selectors.path);
      const root = page.locator(selectors.root);
      await root.locator('.rps-animal-card[data-animalid]').first().waitFor();
      await root.evaluate(element => {
        element.dataset.rpsLayout = 'grid'; element.dataset.rpsView = 'grid'; element.dataset.rpsCardDesign = 'compact';
        element.querySelector('#asm-grid, #asm-plugin-grid').style.gridTemplateColumns = 'repeat(2, minmax(0, 1fr))';
      });
      await page.evaluate(() => new Promise(resolve => requestAnimationFrame(resolve)));
      const overflow = await root.locator('.rps-animal-card').evaluateAll(cards => cards.map(card => ({ width: card.clientWidth, content: card.scrollWidth })));
      assert(overflow.every(card => card.content <= card.width + 1), module + ': dense mobile columns keep all content within each card');
      console.log('PASS ' + module + ': narrow cards adapt to administrator-selected mobile columns');
    }
    assert.deepEqual(errors, [], 'No browser JavaScript errors');
    console.log('Screenshots: ' + output);
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
