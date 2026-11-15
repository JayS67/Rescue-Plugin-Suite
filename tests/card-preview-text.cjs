// Tests the actual browser parser used by both preview renderers, without live feeds.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: process.env.RPS_CHROMIUM || '/usr/bin/chromium' });
  try {
    const page = await browser.newPage();
    for (const module of ['adoptables', 'adopted']) {
      const source = fs.readFileSync(path.join(__dirname, '..', 'includes/modules', module, 'class-module.php'), 'utf8');
      const start = source.indexOf('  function cardExcerpt(');
      const end = source.indexOf('  function cardTemplate(a){', start);
      assert(start > 0 && end > start, `${module} preview helper exists`);
      await page.addScriptTag({ content: source.slice(start, end) });
      const results = await page.evaluate(() => {
        const rich = 'First paragraph\r\nSecond line\r\n\r\n<p>Third &amp; final<br>Fourth line</p><script>window.previewScriptRan=true</script><style>body{display:none}</style>';
        const container = document.createElement('p');
        container.className = 'rps-card-excerpt';
        container.style.whiteSpace = 'pre-line';
        container.textContent = cardExcerpt('<p>First &lt;img src=x onerror=alert(1)&gt;</p><p>Second<br>Third</p>');
        document.body.appendChild(container);
        return {
          rich: cardExcerpt(rich),
          list: cardExcerpt('<div>Intro</div><ul><li>One</li><li>Two</li></ul>'),
          wrapped: container.innerText,
          imageCount: container.querySelectorAll('img').length,
          scriptRan: !!window.previewScriptRan,
          shortened: cardExcerpt('One\nTwo\nThree Four Five', 4),
          longWord: cardExcerpt('🐾'.repeat(400)),
          unchanged: cardExcerpt('A short story.\n\nA new paragraph.'),
          safe: cardExcerpt('<img src="https://example.invalid/should-not-load"><b>Story</b>'),
        };
      });
      assert.equal(results.rich, 'First paragraph\nSecond line\n\nThird & final\nFourth line');
      assert.equal(results.list, 'Intro\nOne\nTwo');
      assert.equal(results.wrapped, 'First <img src=x onerror=alert(1)>\nSecond\nThird');
      assert.equal(results.imageCount, 0, 'Encoded markup stays readable text');
      assert.equal(results.scriptRan, false, 'Feed scripts never run');
      assert.equal(results.shortened, 'One\nTwo\nThree Four…', 'Word limit retains source breaks and announces truncation');
      assert.equal(Array.from(results.longWord).length, 361, 'Long words have a Unicode-safe character bound');
      assert(results.longWord.endsWith('…'));
      assert.equal(results.unchanged, 'A short story.\n\nA new paragraph.');
      assert.equal(results.safe, 'Story');
      console.log(`PASS ${module}: preview line breaks, safe markup/entities, word and Unicode limits`);
    }
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
