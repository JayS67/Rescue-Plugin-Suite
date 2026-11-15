/* Disposable WordPress fixture. Credentials are supplied through environment variables. */
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const base = process.env.RPS_BASE_URL || 'http://127.0.0.1:8086';
const adminUrl = base + '/wp-admin/admin.php';
(async () => {
  assert(process.env.RPS_ADMIN_USER && process.env.RPS_ADMIN_PASSWORD, 'Supply fixture administrator credentials');
  assert(process.env.RPS_SUBSCRIBER_USER && process.env.RPS_SUBSCRIBER_PASSWORD, 'Supply fixture subscriber credentials');
  const browser = await chromium.launch({ headless:true, ...(process.env.RPS_CHROMIUM ? {executablePath:process.env.RPS_CHROMIUM} : {}) });
  try {
    async function login(page, user, password) {
      await page.goto(base + '/wp-login.php');
      await page.locator('#user_login').fill(user); await page.locator('#user_pass').fill(password);
      await Promise.all([page.waitForURL(/\/wp-admin\//), page.locator('#wp-submit').click()]);
    }
    const page = await browser.newPage({viewport:{width:1440,height:1000}});
    const errors=[]; page.on('pageerror', error=>errors.push(error.message));
    await login(page, process.env.RPS_ADMIN_USER, process.env.RPS_ADMIN_PASSWORD);
    await page.goto(adminUrl + '?page=plugin-ui-suite');
    const menu = page.locator('#toplevel_page_plugin-ui-suite');
    assert.equal(await menu.count(),1,'Exactly one top-level suite menu');
    assert.equal(await page.locator('#menu-settings .wp-submenu a[href*="page=plugin-ui-suite"]').count(),0,'No duplicate Settings menu entry');
    const sections=[['General','global','plugin-ui-suite'],['Integrations','data-source','plugin-ui-suite-integrations'],['Widgets','adoptables','plugin-ui-suite-widgets'],['Forms','forms','plugin-ui-suite-forms'],['Donations','payments','plugin-ui-suite-donations'],['Diagnostics','diagnostics','plugin-ui-suite-diagnostics'],['Updates','updates','plugin-ui-suite-updates'],['Help Centre','help','plugin-ui-suite-help']];
    for (const [label, tab, slug] of sections) {
      await menu.locator('.wp-submenu a').filter({hasText:new RegExp('^'+label+'$')}).click();
      await page.locator('.rps-admin').waitFor();
      await page.waitForLoadState('domcontentloaded');
      const current = new URL(page.url());
      assert.equal(current.pathname,'/wp-admin/admin.php');
      assert.equal(current.searchParams.get('page'),'plugin-ui-suite');
      assert.equal(current.searchParams.get('tab') || 'global',tab);
      assert.equal(await menu.locator('.wp-submenu li.current a').getAttribute('href'),'admin.php?page='+slug);
      assert(await page.locator('#plugin-suite-admin-css').count(),'Suite stylesheet loads on new screen');
      assert(await page.locator('#plugin-suite-admin-sortable-js').count(),'Layout keyboard script loads');
      if (tab==='payments') assert(await page.locator('#plugin-payments-js').count(),'Payment admin script loads');
    }
    for (const tab of ['adopted','stats','featured','quiz']) {
      await page.goto(adminUrl + '?page=plugin-ui-suite&tab='+tab);
      assert.equal(await menu.locator('.wp-submenu li.current a').getAttribute('href'),'admin.php?page=plugin-ui-suite-widgets');
    }
    const response = await page.goto(base+'/wp-admin/options-general.php?page=plugin-ui-suite&tab=payments&payments_section=appearance&updated=true&plugin_msg=Menu%20test');
    assert.equal(response.status(),200);
    const current=new URL(response.url());
    assert.equal(current.pathname,'/wp-admin/admin.php');
    for(const [key,value] of [['tab','payments'],['payments_section','appearance'],['updated','true'],['plugin_msg','Menu test']]) assert.equal(current.searchParams.get(key),value);
    assert(await page.locator('.notice-success').filter({hasText:'Settings saved.'}).count());
    assert(await page.locator('.notice-info').filter({hasText:'Menu test'}).count());
    for(const [old,tab] of [['plugin-adoptables-ui','adoptables'],['plugin-adopted-ui','adopted'],['plugin-stats-ui','stats'],['plugin-ui-suite-updates','updates']]) {
      await page.goto(base+'/wp-admin/options-general.php?page='+old);
      assert.equal(new URL(page.url()).searchParams.get('tab'),tab,old+' redirected to '+page.url());
      assert.equal(new URL(page.url()).pathname,'/wp-admin/admin.php');
    }
    for (const [legacy,tab,section] of [['general','payments','general'],['providers','data-source','payment-stripe']]) {
      const redirected=await page.goto(base+'/wp-admin/options-general.php?page=plugin-ui-suite-payments-'+legacy);
      const target=new URL(redirected.url());
      assert.equal(target.pathname,'/wp-admin/admin.php');
      assert.equal(target.searchParams.get('tab'),tab);
      assert.equal(target.searchParams.get(legacy==='providers'?'subtab':'payments_section'),section);
    }
    await page.goto(adminUrl+'?page=plugin-ui-suite&tab=payments&payments_section=appearance');
    const paymentForm=page.locator('form[action$="options.php"]');
    try {
      await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded',timeout:10000}),paymentForm.locator('input[type=submit]').click()]);
    } catch (error) {
      console.error('Payment save URL:',page.url());
      console.error('Invalid payment fields:',await paymentForm.locator(':invalid').evaluateAll(nodes=>nodes.map(el=>({name:el.name,message:el.validationMessage}))));
      throw error;
    }
    assert.equal(new URL(page.url()).pathname,'/wp-admin/admin.php');
    assert.equal(new URL(page.url()).searchParams.get('tab'),'payments');
    assert.equal(new URL(page.url()).searchParams.get('payments_section'),'appearance');
    await page.goto(base+'/wp-admin/options-general.php?page=plugin-ui-suite-setup');
    assert.equal(new URL(page.url()).pathname,'/wp-admin/admin.php');
    assert.equal(new URL(page.url()).searchParams.get('page'),'plugin-ui-suite-setup');
    await page.goto(adminUrl+'?page=plugin-ui-suite&tab=global');
    if(process.env.RPS_MENU_SCREENSHOT) await page.screenshot({path:process.env.RPS_MENU_SCREENSHOT});
    const settingsForm = page.locator('form').filter({has:page.locator('input[name="suite[global][style_behavior]"],select[name="suite[global][style_behavior]"]')});
    await Promise.all([page.waitForURL(/updated=true/),settingsForm.locator('input[type=submit]').click()]);
    assert.equal(new URL(page.url()).pathname,'/wp-admin/admin.php','Settings save returns to top-level page');
    const subscriber=await browser.newPage();
    await login(subscriber,process.env.RPS_SUBSCRIBER_USER,process.env.RPS_SUBSCRIBER_PASSWORD);
    assert.equal(await subscriber.locator('#toplevel_page_plugin-ui-suite').count(),0);
    const denied=await subscriber.goto(adminUrl+'?page=plugin-ui-suite');
    assert.equal(denied.status(),403,'Subscriber cannot access suite settings directly');
    assert.deepEqual(errors,[],'No browser errors');
    console.log('PASS top-level menu, 8 sidebar sections, asset loading, active section, legacy URLs, setup, settings/payment saves and subscriber access restrictions.');
  } finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exitCode=1;});
