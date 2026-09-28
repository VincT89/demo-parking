const fs = require('node:fs');
const path = require('node:path');
const cp = require('node:child_process');
const crypto = require('node:crypto');
const assert = require('node:assert/strict');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '../..');
const directory = path.join(root, 'storage/framework/testing/demo-login-qa');
fs.mkdirSync(path.join(directory, 'views'), {recursive:true});
const checks = [];
const errors = [];
const servers = [];
let browser;

async function serverFor(demo, port) {
    const baseURL = 'http://127.0.0.1:' + port;
    const appName = 'Login QA ' + crypto.randomUUID();
    const env = {...process.env,
        APP_ENV:'testing', APP_DEBUG:'false', APP_NAME:appName, APP_URL:baseURL, ASSET_URL:'',
        APP_KEY:'base64:' + crypto.randomBytes(32).toString('base64'),
        APP_CONFIG_CACHE:path.join(directory, 'unused-config.php'), APP_ROUTES_CACHE:path.join(directory, 'unused-routes.php'),
        VIEW_COMPILED_PATH:path.join(directory, 'views'), DEMO_MODE:demo ? 'true' : 'false',
        DB_CONNECTION:'sqlite', DB_DATABASE:':memory:', DB_URL:'',
        CACHE_STORE:'array', SESSION_DRIVER:'cookie', SESSION_COOKIE:'login_qa_' + port,
        SESSION_DOMAIN:'', SESSION_SECURE_COOKIE:'false', MAIL_MAILER:'array', QUEUE_CONNECTION:'sync', LOG_CHANNEL:'stderr',
    };
    const server = cp.spawn(process.env.PHP_EXECUTABLE || 'php', ['-S', '127.0.0.1:' + port, path.join(__dirname, 'login-router.php')],
        {cwd:path.join(root,'public'), env, windowsHide:true, stdio:['ignore','pipe','pipe']});
    servers.push(server);
    let output = '';
    server.stderr.on('data', b => { output = (output + b).slice(-3000); });
    server.stdout.on('data', () => {});
    for (let attempt=0; attempt<60; attempt++) {
        if (server.exitCode !== null) throw new Error('Isolated login server failed: ' + output);
        try { if ((await fetch(baseURL + '/up')).ok) return {baseURL, appName}; } catch {}
        await new Promise(resolve => setTimeout(resolve, 200));
    }
    throw new Error('Isolated login server was not ready.');
}

async function run() {
    browser = await chromium.launch({headless:true, executablePath:process.env.BROWSER_EXECUTABLE});
    for (const demo of [true, false]) {
        const {baseURL, appName} = await serverFor(demo, demo ? 8767 : 8768);
        const context = await browser.newContext({baseURL, viewport:{width:1440,height:900}});
        await context.route('**/*', route => new URL(route.request().url()).origin === baseURL ? route.continue() : route.abort());
        const page = await context.newPage();
        page.on('pageerror', error => errors.push(error.message));
        assert.equal((await page.goto('/login')).status(), 200);
        assert.equal(await page.title(), appName, 'Only use the dedicated test server');
        for (const [locale, language, ab, b] of [
            ['it', 'Italiano (IT)', 'Accesso A+B', 'Accesso solo B'],
            ['en_GB', 'English (EN)', 'A+B access', 'B-only access'],
            ['nl', 'Nederlands (NL)', 'Toegang tot A+B', 'Alleen toegang tot B'],
        ]) {
            await page.locator('.pm-locale-switcher summary').click();
            await page.getByRole('button', {name:language, exact:true}).click();
            await page.waitForLoadState('networkidle');
            assert.equal(await page.locator('.pm-auth-shell').evaluate(el => getComputedStyle(el).display), 'grid', 'Built login styles are loaded');
            assert.equal(await page.locator('.pm-demo-account').count(), demo ? 2 : 0);
            if (demo) {
                assert.equal(await page.locator('#demo-access-ab').innerText(), ab);
                assert.equal(await page.locator('#demo-access-b').innerText(), b);
                assert.match(await page.locator('.pm-demo-account').nth(0).innerText(), /demo@sodanoconsulting\.it/);
                assert.match(await page.locator('.pm-demo-account').nth(1).innerText(), /socio@sodanoconsulting\.it/);
                assert.equal(await page.locator('.pm-demo-credentials dd').filter({hasText:/^password$/}).count(), 2);
            } else {
                const html = await page.content();
                assert.ok(!html.includes('demo@sodanoconsulting.it') && !html.includes('socio@sodanoconsulting.it'));
            }
            for (const width of [320, 360, 768, 1440]) {
                await page.setViewportSize({width, height:width < 768 ? 740 : 900});
                const layout = await page.evaluate(() => ({
                    scroll:document.documentElement.scrollWidth,
                    panel:document.querySelector('.pm-auth-panel').getBoundingClientRect().toJSON(),
                    credentials:[...document.querySelectorAll('.pm-demo-credentials')].map(el => ({scroll:el.scrollWidth, width:el.clientWidth})),
                    labels:[...document.querySelectorAll('.pm-demo-credentials dt')].map(el => {
                        const range = document.createRange(); range.selectNodeContents(el);
                        return {labelRight:range.getBoundingClientRect().right, valueLeft:el.nextElementSibling.getBoundingClientRect().left};
                    }),
                }));
                if (layout.scroll > width + 1) {
                    const overflow = await page.evaluate(() => [...document.body.querySelectorAll('*')].map(el => {
                        const rect = el.getBoundingClientRect();
                        const style = getComputedStyle(el);
                        return {tag:el.tagName, class:el.className, left:rect.left, right:rect.right, width:rect.width, minWidth:style.minWidth, grid:style.gridTemplateColumns};
                    }).filter(el => el.width && (el.left < -1 || el.right > innerWidth + 1)));
                    console.error(JSON.stringify({demo, locale, width, layout, overflow}, null, 2));
                    await page.screenshot({path:path.join(directory, 'overflow-' + locale + '-' + width + '.png'), fullPage:true, animations:'disabled'});
                }
                assert.ok(layout.scroll <= width + 1, locale + ' horizontal overflow at ' + width);
                assert.ok(layout.panel.left >= -1 && layout.panel.right <= width + 1, 'Login panel fits viewport');
                for (const credential of layout.credentials) assert.ok(credential.scroll <= credential.width + 1, 'Credentials are not clipped');
                for (const label of layout.labels) assert.ok(label.labelRight + 4 <= label.valueLeft, 'Credential labels and values have a readable gap');
                assert.ok(await page.locator('.pm-auth-logo').isVisible());
                checks.push({demo, locale, width, scrollWidth:layout.scroll});
                if (demo) await page.screenshot({path:path.join(directory, locale + '-' + width + '.png'), fullPage:true, animations:'disabled'});
            }
            await page.locator('#password').fill('Only-a-UI-test');
            const toggle = page.locator('.pm-password-toggle');
            await toggle.click(); assert.equal(await page.locator('#password').getAttribute('type'), 'text');
            await toggle.click(); assert.equal(await page.locator('#password').getAttribute('type'), 'password');
            await page.locator('#password').fill('');
        }
        await context.close();
        console.log('Verified login with demo mode ' + demo);
    }
    assert.deepEqual(errors, []);
    fs.writeFileSync(path.join(directory, 'report.json'), JSON.stringify({checks, errors}, null, 2));
    console.log(JSON.stringify({checks:checks.length, errors}));
}

run().catch(error => {console.error(error); process.exitCode=1;}).finally(async () => {
    if (browser) await browser.close();
    for (const server of servers) server.kill();
});
