/* Disposable WP fixture only. Hosted forms/animal feeds are mocked; no submission or checkout. */
const assert = require('node:assert/strict');
const path = require('node:path');
const fs = require('node:fs');
const { chromium } = require('playwright');
const base = process.env.RPS_BASE_URL || 'http://127.0.0.1:8086';
const output = process.env.RPS_SCREENSHOT_DIR || '/tmp/rps-experiences';
const selectors = ['.asm-featured-animal-widget','.asm-adoption-quiz-widget','.plugin-payments-widget','.rescue-suite-form-wrap'];
const animals = [{ANIMALID:42,ANIMALNAME:'Mittens',ANIMALAGE:'2 years',SEXNAME:'Female',SPECIESID:2,SPECIESNAME:'Cat',BREEDNAME:'Domestic shorthair',ANIMALCOMMENTS:'A gentle companion looking for a home.'}];
(async()=>{
  fs.mkdirSync(output,{recursive:true});
  const browser=await chromium.launch({headless:true,executablePath:process.env.RPS_CHROMIUM||'/usr/bin/chromium'});
  const page=await browser.newPage({reducedMotion:'reduce'}), errors=[];
  page.on('pageerror',error=>errors.push(error.message));
  await page.route('https://service.sheltermanager.com/asmservice**',route=>route.fulfill({contentType:'text/javascript',body:`document.getElementById('asm3-onlineform').innerHTML='<form><label for="review-name">Your name</label><input id="review-name" autocomplete="name"><label for="review-email">Email address</label><input id="review-email" type="email" autocomplete="email"><label for="review-notes">Tell us about your home</label><textarea id="review-notes" rows="4"></textarea><button type="button">Send enquiry</button></form>';`}));
  await page.route('**/wp-json/plugin/v1/**',route=>route.request().url().includes('animal-image')?route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="900" height="600"><rect width="900" height="600" fill="#e1d8e9"/></svg>'}):route.fulfill({json:animals}));
  try{
    for(const width of [390,768,1440]){
      await page.setViewportSize({width,height:1100});
      await page.goto(base+'/widget-review/');
      for(const selector of selectors) await page.locator(selector).first().waitFor();
      await page.waitForFunction(()=>[...document.querySelectorAll('[id$="-asm-featured-name"]')].every(el=>el.textContent!=='Loading…'));
      if(process.env.RPS_EXPERIENCE_INJECT==='1') await page.addStyleTag({path:path.join(__dirname,'../assets/css/widget-experiences.css')});
      else assert(await page.locator('link[href*="widget-experiences.css"]').count(),'The experience stylesheet is enqueued');
      await page.addScriptTag({path:require.resolve('axe-core/axe.min.js')});
      for(const selector of selectors){
        const widget=page.locator(selector).first();
        const typography=await widget.evaluate(el=>({widget:getComputedStyle(el).fontFamily,site:getComputedStyle(el.parentElement).fontFamily}));
        assert.equal(typography.widget,typography.site,selector+' inherits the site font');
        assert.equal(await widget.evaluate(el=>getComputedStyle(el).boxShadow),'none',selector+' uses a quiet surface');
        const violations=await widget.evaluate(async el=>(await axe.run(el,{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa']}})).violations.map(v=>({id:v.id,nodes:v.nodes.map(n=>n.html)})));
        assert.deepEqual(violations,[],width+'px '+selector+' accessibility');
        await widget.screenshot({path:path.join(output,selector.slice(1)+'-'+width+'.png')});
      }
      const featured=page.locator('.asm-featured-animal-widget').first();
      const photo=await featured.locator('.rps-featured-media img').evaluate(el=>{const r=el.getBoundingClientRect();return{w:r.width,h:r.height,fit:getComputedStyle(el).objectFit};});
      assert(Math.abs(photo.w-photo.h)<1,'Featured photo retains a square frame');
      assert(photo.w<=281,'Featured photo is bounded on large pages');
      assert.equal(photo.fit,'cover','Featured photo fills its square frame');
      const quiz=page.locator('.asm-adoption-quiz-widget').first();
      await quiz.locator('[id$="-asm-quiz-run"]').click();
      await quiz.locator('.rps-match-card').first().waitFor();
      const match=await quiz.locator('.rps-match-card').first().evaluate(el=>{const image=el.querySelector('img').getBoundingClientRect();return{height:el.getBoundingClientRect().height,imageWidth:image.width,imageHeight:image.height};});
      assert(match.imageWidth<=145&&Math.abs(match.imageWidth-match.imageHeight)<1,'Quiz results keep a small square photo');
      assert(match.height<=350,'A single quiz result must not become a full-width photo banner');
      assert(await quiz.locator('select').first().evaluate(el=>el.getBoundingClientRect().height>=44),'Quiz choices retain touch targets');
      await quiz.locator('select').first().focus();
      await page.keyboard.press('ArrowDown');
      assert(await quiz.locator('select').first().evaluate(el=>el===document.activeElement),'Native keyboard interaction retained');
      const donation=page.locator('.plugin-payments-widget').first();
      const choice=donation.locator('[data-payment-amount]:visible').first();
      await choice.click();
      assert.equal(await choice.getAttribute('aria-pressed'),'true');
      assert(await choice.evaluate(el=>el.getBoundingClientRect().height>=44),'Donation choices retain touch targets');
      assert(await donation.locator('[data-payment-impact]').textContent(),'Donation feedback remains visible after selection');
      for(const selector of selectors){
        const widget=page.locator(selector).first();
        await widget.evaluate(el=>{el.dataset.rpsTransparent='true';el.dataset.rpsShowTitles='false';el.dataset.rpsShowSubtitles='false';});
        assert.equal(await widget.evaluate(el=>getComputedStyle(el).backgroundColor),'rgba(0, 0, 0, 0)',selector+' transparent option retained');
        const hiddenTitle=widget.locator('.rps-widget-title').first();
        assert(await hiddenTitle.evaluate(el=>getComputedStyle(el).clipPath==='inset(50%)'),selector+' title visibility retained');
      }
      assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'No horizontal page overflow at '+width+'px');
      console.log('PASS '+width+'px: featured, quiz, donation, form polish, accessibility and presentation controls');
    }
    assert.deepEqual(errors,[],'No browser errors');
    console.log('Screenshots: '+output);
  } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});
