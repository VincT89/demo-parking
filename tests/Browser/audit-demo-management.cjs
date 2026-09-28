const fs = require('node:fs');
const path = require('node:path');
const cp = require('node:child_process');
const crypto = require('node:crypto');
const assert = require('node:assert/strict');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '../..');
const directory = path.join(root, 'storage/framework/testing');
const baseURL = 'http://127.0.0.1:8766';
const env = {...process.env,
    APP_ENV:'testing', APP_DEBUG:'true', APP_URL:baseURL, ASSET_URL:'',
    APP_KEY:'base64:'+crypto.randomBytes(32).toString('base64'),
    APP_CONFIG_CACHE:path.join(directory,'browser-config.php'), APP_ROUTES_CACHE:path.join(directory,'browser-routes.php'),
    VIEW_COMPILED_PATH:path.join(directory,'views'), DEMO_MODE:'true', DEMO_API_LATENCY_MS:'0',
    DB_CONNECTION:'sqlite', DB_DATABASE:path.join(directory,'demo-management-browser.sqlite'), DB_URL:'',
    CACHE_STORE:'array', SESSION_DRIVER:'database', SESSION_COOKIE:'demo_management_qa', SESSION_DOMAIN:'', SESSION_SECURE_COOKIE:'false',
    MAIL_MAILER:'array', QUEUE_CONNECTION:'database', LOG_CHANNEL:'stderr',
};
fs.mkdirSync(env.VIEW_COMPILED_PATH,{recursive:true});
const fixturePath=path.join(directory,'demo-management-fixtures.json');
if(!fs.existsSync(fixturePath)) cp.execFileSync(process.env.PHP_EXECUTABLE || 'php',['tests/Browser/prepare-demo.php'],{cwd:root,env,stdio:'inherit'});
const fixture=JSON.parse(fs.readFileSync(fixturePath,'utf8'));
const server=cp.spawn(process.env.PHP_EXECUTABLE || 'php',['-S','127.0.0.1:8766',path.join(root,'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')],{cwd:path.join(root,'public'),env,windowsHide:true,stdio:['ignore','pipe','pipe']});
let serverLog='';
server.stdout.on('data',b=>{serverLog=(serverLog+b).slice(-8000)});
server.stderr.on('data',b=>{serverLog=(serverLog+b).slice(-8000)});
const output=path.join(directory,'demo-browser-qa'); fs.mkdirSync(output,{recursive:true});
const failures=[], checks=[], jsErrors=[];
let browser;
function ensure(value,message) { if(!value) failures.push(message); }
async function login(email) {
    const context=await browser.newContext({baseURL,viewport:{width:1440,height:1000}});
    await context.route('**/*',route=>new URL(route.request().url()).origin===baseURL ? route.continue() : route.abort());
    const page=await context.newPage();
    page.on('pageerror',e=>jsErrors.push(e.message));
    await page.goto('/login');
    await page.locator('input[name=email]').fill(email);
    await page.locator('input[name=password]').fill('Only-browser-fixture-2026');
    await page.locator('form[action$="/login"] button[type=submit]').click();
    await page.waitForURL('**/dashboard');
    return {context,page};
}
async function run() {
    for(let i=0;i<60;i++) {
        if(server.exitCode!==null) throw new Error('Test server failed: '+serverLog);
        try { if((await fetch(baseURL+'/up')).ok) break; } catch {}
        await new Promise(r=>setTimeout(r,200));
    }
    browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE});
    const {page,context}=await login('cliente@example.test');
    await page.locator('#active-parking').selectOption(String(fixture.B.parking));
    await page.locator('.pm-parking-switch button').click();
    await page.waitForLoadState('networkidle');
    ensure(await page.locator('#active-parking').inputValue()===String(fixture.B.parking),'Owner switching to B failed');
    const pages=[['dashboard','/dashboard'],['users','/management/users'],['activity','/management/activity'],['new-parking','/admin/parkings/create'],['platform','/platforms/'+fixture.B.apiPlatform+'/edit'],['customers','/customers'],['customer','/customers/'+fixture.B.customer],['garage','/garage'],['subscriptions','/garage/subscriptions'],['subscription','/garage/subscriptions/'+fixture.B.subscription],['stays','/garage/stays'],['new-stay','/garage/stays/create'],['rates','/admin/garage-rates'],['shuttles','/shuttles'],['shuttle-settings','/admin/shuttles/settings'],['invoices','/invoices'],['invoice','/invoices/'+fixture.B.invoice],['settings','/admin/operational-settings'],['reservations','/reservations'],['calendar','/calendar'],['analytics','/analytics']];
    const selected=process.env.AUDIT_PAGES ? pages.filter(([name])=>process.env.AUDIT_PAGES.split(',').includes(name)) : pages;
    for(const [locale,language] of [['it','Italiano (IT)'],['en_GB','English (EN)'],['nl','Nederlands (NL)']].filter(([locale])=>!process.env.AUDIT_LOCALES || process.env.AUDIT_LOCALES.split(',').includes(locale))) {
        await page.setViewportSize({width:1440,height:1000});
        await page.goto('/management/users');
        await page.locator('.pm-locale-switcher summary').click();
        await page.getByRole('button',{name:language,exact:true}).click();
        await page.waitForLoadState('networkidle');
        const title={it:'Utenti',en_GB:'Users',nl:'Gebruikers'}[locale];
        ensure((await page.locator('.pm-page-title').innerText())===title,'Users language '+locale);
        for(const width of (process.env.AUDIT_WIDTHS ? process.env.AUDIT_WIDTHS.split(',').map(Number) : [360,768,1440])) {
            await page.setViewportSize({width,height:1000});
            for(const [name,url] of selected) {
                const response=await page.goto(url); await page.waitForLoadState('networkidle');
                ensure(response.status()===200,locale+'/'+width+'/'+name+' HTTP '+response.status());
                if(response.status()!==200) continue;
                const layout=await page.evaluate(()=>{
                    const root=document.documentElement;
                    const title=document.querySelector('.pm-page-title');
                    const r=title?.getBoundingClientRect();
                    return {width:innerWidth,scroll:root.scrollWidth,title:r?{left:r.left,right:r.right,height:r.height,scroll:title.scrollHeight,client:title.clientHeight}:null};
                });
                ensure(layout.scroll<=width+1,locale+'/'+width+'/'+name+' page overflow '+layout.scroll);
                if(layout.scroll>width+1) console.log('Overflow details',locale,name,width,await page.evaluate(()=>Array.from(document.querySelectorAll('main *')).filter(el=>el.getBoundingClientRect().right>innerWidth).slice(0,20).map(el=>({tag:el.tagName,cls:el.className,width:el.getBoundingClientRect().width,left:el.getBoundingClientRect().left,right:el.getBoundingClientRect().right,position:getComputedStyle(el).position,overflow:getComputedStyle(el).overflowX,text:el.textContent.slice(0,70)}))));
                if(layout.title) ensure(layout.title.left>=-1 && layout.title.right<=width+1 && layout.title.scroll<=layout.title.client+1,locale+'/'+width+'/'+name+' clipped title');
                checks.push({locale,width,page:name,status:response.status(),scrollWidth:layout.scroll});
                if(['users','new-parking','shuttles','customer'].includes(name) && width!==768) await page.screenshot({path:path.join(output,locale+'-'+width+'-'+name+'.png'),fullPage:true,animations:'disabled'});
            }
        }
        console.log('Completed '+locale+' responsive page checks');
    }
    await page.setViewportSize({width:1440,height:1000}); await page.goto('/management/users');
    const password=page.locator('#member-password');
    await password.fill('Test-only-password');
    await password.locator('..').locator('button').click(); ensure(await password.getAttribute('type')==='text','Password reveal');
    await password.locator('..').locator('button').click(); ensure(await password.getAttribute('type')==='password','Password hide');
    const memberEmail='ui-member-'+Date.now()+'@example.test';
    await page.locator('#member-name').fill('Utente dimostrativo di test');
    await page.locator('#member-email').fill(memberEmail);
    await page.locator('#member-password').fill('Only-browser-fixture-2026');
    await page.locator('#member-password-confirmation').fill('Only-browser-fixture-2026');
    await page.locator('#member-role').selectOption('staff');
    await page.locator('form[action$="/management/users"] button[type=submit]').click();
    await page.waitForLoadState('networkidle');
    const memberRow=page.locator('.pm-members-table tbody tr').filter({hasText:memberEmail});
    ensure(await memberRow.count()===1,'New B user is visible in the member list');
    const member=await login(memberEmail);
    ensure(await member.page.locator('#active-parking option').count()===1,'New B user has only B');
    ensure((await member.page.goto('/management/users')).status()===403,'Staff cannot administer users');
    await memberRow.locator('select[name=role]').selectOption('admin');
    await memberRow.locator('.pm-member-role-form button').click(); await page.waitForLoadState('networkidle');
    ensure((await member.page.goto('/management/users')).status()===200,'Promoted administrator has access');
    page.once('dialog',dialog=>dialog.accept());
    await page.locator('.pm-members-table tbody tr').filter({hasText:memberEmail}).locator('.pm-btn-danger').click();
    await page.waitForLoadState('networkidle');
    ensure((await member.page.goto('/dashboard')).status()===403,'Revoked member no longer has access');
    await member.context.close(); await page.goto('/management/users');
    await page.setViewportSize({width:360,height:800});
    await page.locator('.pm-mobile-menu-btn').click();
    ensure(await page.locator('#pm-sidebar').evaluate(el=>el.classList.contains('mobile-open')),'Mobile menu open');
    await page.keyboard.press('Escape');
    ensure(!(await page.locator('#pm-sidebar').evaluate(el=>el.classList.contains('mobile-open'))),'Mobile menu close');
    await page.setViewportSize({width:1440,height:1000});
    await page.goto('/management/users');
    await page.goto('/dashboard');
    const movement=page.locator('input[id="check_in_'+fixture.B.reservation+'"]');
    const movementBefore=await movement.isChecked();
    await page.route('**/reservations/*/toggle-movement',route=>route.fulfill({status:409,contentType:'application/json',body:JSON.stringify({message:'Test only'})}));
    await movement.click();
    await page.waitForFunction(()=>!document.querySelector('#pm-request-error').hidden);
    ensure(await movement.isChecked()===movementBefore,'Movement checkbox rolls back after rejected save');
    ensure((await page.locator('#pm-request-error').innerText())===await page.locator('html').getAttribute('data-context-changed'),'Stale movement message uses current language');
    await page.unroute('**/reservations/*/toggle-movement');
    await page.route('**/calendar/data?*',route=>route.fulfill({status:500,body:'Test only'}));
    await page.goto('/calendar');
    await page.locator('#calendar-error').waitFor({state:'visible'});
    ensure(await page.locator('#calendar-error').isVisible(),'Calendar request error is shown');
    await page.unroute('**/calendar/data?*');
    await page.locator('#calendar-error button').click();
    await page.locator('#calendar-error').waitFor({state:'hidden'});
    await page.waitForLoadState('networkidle');
    ensure(!(await page.locator('#calendar-error').isVisible()),'Calendar retry recovers');
    await page.goto('/management/users');
    const oldPage=await context.newPage(); await oldPage.goto('/customers/create');
    await page.locator('#active-parking').selectOption(String(fixture.A.parking)); await page.locator('.pm-parking-switch button').click(); await page.waitForLoadState('networkidle');
    const stale=await oldPage.evaluate(async()=>{ const r=await fetch('/customers',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:JSON.stringify({name:'Stale test'})}); return r.status; });
    ensure(stale===409,'Old-tab save should return 409, got '+stale); await oldPage.close();
    const partner=await login('socio@example.test');
    ensure(await partner.page.locator('#active-parking option').count()===1,'Partner must have only B option');
    for(const url of ['/customers/'+fixture.A.customer,'/garage/subscriptions/'+fixture.A.subscription,'/invoices/'+fixture.A.invoice]) {
        ensure((await partner.page.goto(url)).status()===404,'Partner foreign access '+url);
    }
    await page.goto('/admin/parkings/create');
    const suffix=String(Date.now());
    await page.locator('input[name=separate_management]').check();
    await page.locator('#management-name').fill('Gestione browser test '+suffix);
    await page.locator('#name').fill('Parcheggio browser test '+suffix);
    await page.locator('#total_spots').fill('10');
    await page.locator('form[action$="/admin/parkings"] button[type=submit]').click();
    await page.waitForLoadState('networkidle');
    ensure((await page.locator('#active-parking option:checked').innerText()).includes(suffix),'New separate parking becomes active');
    await page.goto('/management/users');
    ensure(await page.locator('.pm-members-table tbody tr').count()===1,'Separate management grants access only to creator');
    await partner.page.goto('/dashboard');
    ensure(await partner.page.locator('#active-parking option').count()===1,'Partner cannot see newly created separate management');
    await context.close(); await partner.context.close();
    ensure(jsErrors.length===0,'JavaScript errors: '+jsErrors.join('; '));
    fs.writeFileSync(path.join(output,'report.json'),JSON.stringify({checks,failures,jsErrors},null,2));
    console.log(JSON.stringify({checks:checks.length,failures,jsErrors},null,2));
    if(failures.length) process.exitCode=1;
}
run().catch(error=>{console.error(error);console.error(serverLog);process.exitCode=1;}).finally(async()=>{if(browser) await browser.close(); server.kill();});
