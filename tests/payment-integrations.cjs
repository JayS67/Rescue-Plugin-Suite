/* Disposable WordPress: read-only by default; RPS_WP_CLI enables reversible real Settings API save tests. */
const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const {chromium}=require('playwright');
const base=process.env.RPS_BASE_URL||'http://127.0.0.1:8086';
const providers={stripe:'Stripe',paypal:'PayPal',square:'Square',gocardless:'GoCardless',sumup:'SumUp'};
const option='plugin_payments_settings_v1',audit='plugin_payments_audit_v1';
const wp=(args)=>execFileSync(process.env.RPS_WP_CLI,args,{encoding:'utf8',stdio:['ignore','pipe','pipe']}).trim();
const read=()=>JSON.parse(wp(['eval','echo wp_json_encode(Plugin_Payments_Module::settings());']));
const write=(key,value)=>value===null?wp(['option','delete',key]):wp(['option','update',key,JSON.stringify(value),'--format=json']);
(async()=>{
  assert(process.env.RPS_ADMIN_USER&&process.env.RPS_ADMIN_PASSWORD,'Supply disposable administrator credentials');
  const browser=await chromium.launch({headless:true,executablePath:process.env.RPS_CHROMIUM||'/usr/bin/chromium'});
  let fixture;
  try {
    if(process.env.RPS_WP_CLI){
      fixture=JSON.parse(wp(['eval','echo wp_json_encode(["original"=>get_option(Plugin_Payments_Module::OPTION_KEY,null),"audit"=>get_option(Plugin_Payments_Module::AUDIT_KEY,null),"settings"=>Plugin_Payments_Module::settings(),"fields"=>array_map(static function($p){return $p->get_configuration_fields();},Plugin_Payments_Module::gateway_manager()->all())]);']));
      const seed=fixture.settings;
      seed.active_provider='stripe';
      for(const [id,fields]of Object.entries(fixture.fields)){
        seed.provider_settings[id]={enabled:1};
        for(const [key,field]of Object.entries(fields))seed.provider_settings[id][key]=field.type==='checkbox'?1:key==='secret_key'?'sk_test_fixture':key==='publishable_key'?'pk_test_fixture':`fixture-${id}-${key}`;
      }
      write(option,seed);
    }
    const page=await browser.newPage();
    const errors=[];page.on('pageerror',e=>errors.push(e.message));
    await page.goto(base+'/wp-login.php');
    await page.locator('#user_login').fill(process.env.RPS_ADMIN_USER);await page.locator('#user_pass').fill(process.env.RPS_ADMIN_PASSWORD);
    await Promise.all([page.waitForURL(/\/wp-admin\//),page.locator('#wp-submit').click()]);
    for(const [id,name]of Object.entries(providers)){
      const response=await page.goto(base+'/wp-admin/admin.php?page=plugin-ui-suite&tab=data-source&subtab=payment-'+id);
      assert.equal(response.status(),200);
      assert(!/critical error|Fatal error|Uncaught Error/.test(await page.locator('body').innerText()),id+' renders without a PHP fatal');
      const panel=page.locator('.plugin-suite-card').filter({has:page.getByRole('heading',{name:name+' integration',exact:true})});
      await panel.waitFor();
      assert(await page.getByRole('button',{name:'Save integration',exact:true}).isVisible(),id+' save button renders');
      for(const control of await panel.locator('input[type=password]').all())assert.equal(await control.inputValue(),'','Stored secrets remain masked');
      for(const control of await panel.locator('input:not([type=hidden])').all())assert(await control.evaluate(el=>el.labels&&el.labels.length>0),'Every integration input has a label');
      if(['stripe','paypal','gocardless'].includes(id))assert(await panel.locator('code').filter({hasText:'/'+id+'-webhook'}).count(),id+' valid webhook route appears');
      else assert(!await panel.locator('code').filter({hasText:'/'+id+'-webhook'}).count(),id+' does not advertise an absent webhook');
      if(fixture){
        const before=read();
        const enabled=page.locator(`[name="${option}[provider_settings][${id}][enabled]"]`);
        await enabled.setChecked(false);
        for(const [key,field]of Object.entries(fixture.fields[id]))if(field.type==='checkbox')await page.locator(`[name="${option}[provider_settings][${id}][${key}]"]`).setChecked(false);
        await Promise.all([page.waitForURL(/settings-updated=true/),page.getByRole('button',{name:'Save integration',exact:true}).click()]);
        assert.equal(new URL(page.url()).searchParams.get('subtab'),'payment-'+id,'Save returns to the same integration');
        const after=read(),expected=structuredClone(before);
        expected.provider_settings[id].enabled=0;
        for(const [key,field]of Object.entries(fixture.fields[id]))if(field.type==='checkbox')expected.provider_settings[id][key]=0;
        assert.deepEqual(after,expected,id+' actual settings save changes only the selected provider checkboxes and keeps secrets');
      }
      console.log('PASS '+id+' integration: full render, named inputs, masked secrets'+(fixture?', real save and unrelated settings preserved':''));
    }
    assert.deepEqual(errors,[],'No browser exceptions');
    if(process.env.RPS_SUBSCRIBER_USER&&process.env.RPS_SUBSCRIBER_PASSWORD){
      const subscriber=await browser.newPage();
      await subscriber.goto(base+'/wp-login.php');await subscriber.locator('#user_login').fill(process.env.RPS_SUBSCRIBER_USER);await subscriber.locator('#user_pass').fill(process.env.RPS_SUBSCRIBER_PASSWORD);
      await Promise.all([subscriber.waitForURL(/\/wp-admin\//),subscriber.locator('#wp-submit').click()]);
      const response=await subscriber.goto(base+'/wp-admin/admin.php?page=plugin-ui-suite&tab=data-source&subtab=payment-stripe');
      assert.equal(response.status(),403,'Subscriber cannot access integration settings');
      console.log('PASS integration permission restrictions');
    }
  }finally{
    if(fixture){write(option,fixture.original);write(audit,fixture.audit);console.log('Restored original disposable payment settings and audit.');}
    await browser.close();
  }
})().catch(e=>{console.error(e);process.exitCode=1;});
