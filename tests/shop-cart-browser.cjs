// Local WordPress integration check. Requires [rescue_shop] and a published e-card.
// Sends no checkout/payment requests, uploads, or emails.
const assert = require('node:assert/strict');
const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', headless: true, args: ['--no-sandbox'] });
  const context = await browser.newContext();
  const page = await context.newPage();
  try {
    const url = process.env.SHOP_TEST_URL || 'http://127.0.0.1:8086/shop-accessibility-review/';
    await page.goto(url, { waitUntil: 'networkidle' });
    const product = await page.locator('.ssshop-card [data-ssshop-add]').first().getAttribute('data-ssshop-add');
    assert(product, 'A published e-card fixture is required');
    const result = await page.evaluate(async productId => {
      const text = 'A meaningful, personalised card message. '.repeat(20);
      const pages = Array.from({ length: 6 }, (_, pageIndex) => ({ blocks: Array.from({ length: 10 }, (_, blockIndex) => ({ type: 'text', text: `Page ${pageIndex + 1}, block ${blockIndex + 1}: ${text}`, x: 50, y: 50, w: 55, h: 20, font: 'sans', font_size: 20, colour: '#48105f', align: 'left' })) }));
      const payload = {
        action: 'ssshop_add_cart', nonce: RescueShop.nonce, product_id: productId,
        recipient_name: 'Local cart test', recipient_email: 'recipient@example.invalid',
        sender_name: 'Local fixture', buyer_email: 'buyer@example.invalid',
        message: text, message_pages: JSON.stringify([text]), card_pages: JSON.stringify(pages),
        delivery_date: '2099-12-31', delivery_time: '12:30', card_mode: 'standard', card_reveal: 'none'
      };
      const response = await fetch(RescueShop.ajaxUrl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams(payload) });
      return { json: await response.json(), payloadBytes: new TextEncoder().encode(JSON.stringify(payload)).length };
    }, product);
    assert(result.payloadBytes > 32768, 'Exercise a rich design far beyond the browser cookie limit');
    assert.equal(result.json.success, true, 'Rich e-card can be added to the basket');
    assert.equal(Number(result.json.data.count), 1);
    const cookie = (await context.cookies()).find(item => item.name === 'ssshop_cart');
    assert(cookie && cookie.value.length < 150, 'Only a short opaque cart identifier belongs in the cookie');
    assert(cookie.httpOnly, 'Cart identifier is HttpOnly');
    assert(!cookie.value.includes('recipient') && !cookie.value.includes('buyer'), 'Personal details must stay out of the cookie');
    await page.reload({ waitUntil: 'networkidle' });
    assert.equal((await page.locator('[data-ssshop-cart-count]').first().textContent()).trim(), '1', 'Rich basket persists across a real browser reload');
    assert.equal((await context.cookies()).find(item => item.name === 'ssshop_cart').value, cookie.value, 'Reload retains the same cart');
    console.log(`Rich shop design (${result.payloadBytes} bytes) persisted across reload using a ${cookie.value.length}-character HttpOnly cart identifier.`);
  } finally {
    await context.close();
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
