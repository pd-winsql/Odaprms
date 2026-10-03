const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright');
(async () => {
    const html = execFileSync('C:/xampp/php/php.exe', ['tests/treatmentPostponementTest.php', 'render'], { encoding: 'utf8', maxBuffer: 8 * 1024 * 1024 });
    const inlineScripts = [...html.matchAll(/<script(?:\s[^>]*)?>([\s\S]*?)<\/script>/g)].map(match => match[1]);
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    try {
        for (const width of [1440, 390]) {
            const page = await browser.newPage({ viewport: { width, height: 950 } });
            await page.route('**/qa-postponement-preview', route => route.fulfill({ contentType: 'text/html', body: '<html></html>' }));
            await page.goto('http://localhost/Capstone%20System/qa-postponement-preview');
            const errors = []; page.on('pageerror', e => errors.push(e.message));
            let submissions = 0;
            await page.route('**/treatmentPostponementController.php', async route => {
                submissions++;
                assert(route.request().postData().includes('Elevated blood pressure'));
                await route.fulfill({ json: { success: true, message: 'Treatment postponed. Deposit retained for rebooking.' } });
            });
            await page.setContent('<base href="http://localhost/Capstone%20System/apps/views/admin/dashboard.php"><body class="vd-dash-body"><div class="vd-dash-content">' + html.replace(/<script(?:\s[^>]*)?>[\s\S]*?<\/script>/g, '') + '</div></body>');
            for (const file of ['bootstrap.min.css','styles.css','dashboard.css','ui-refinements.css']) await page.addStyleTag({ content: fs.readFileSync('public/css/' + file, 'utf8') });
            await page.addScriptTag({ content: fs.readFileSync('public/js/bootstrap.bundle.min.js', 'utf8') });
            await page.addScriptTag({ content: fs.readFileSync('public/js/loading.js', 'utf8') });
            await page.addScriptTag({ content: fs.readFileSync('public/js/action-modal.js', 'utf8') });
            await page.addStyleTag({ url: 'https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/dist/tabler-icons.min.css' });
            await page.addStyleTag({ url: 'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600&family=Jost:wght@400;500&display=swap' });
            await page.evaluate(() => { window.vdAppUrl = value => 'http://localhost/Capstone%20System/' + value; window.showToast = message => { window.qaToast = message; }; });
            for (const script of inlineScripts) await page.addScriptTag({ content: script });
            assert.deepEqual(errors, [], 'Queue initialization must succeed');
            const trigger = page.locator('[data-postpone-treatment][data-patient="Postponement QA Patient"]');
            await trigger.click();
            const modal = page.locator('#staffActionModal');
            await modal.waitFor({ state: 'visible' });
            await page.locator('#staffActionModalConfirm').click();
            assert.equal(submissions, 0, 'Empty reason cannot submit');
            await modal.locator('textarea').fill('Elevated blood pressure at assessment.');
            const bounds = await modal.locator('.modal-dialog').boundingBox();
            assert(bounds.x >= 0 && bounds.x + bounds.width <= width + 1, 'Dialog fits the viewport');
            await page.keyboard.press('Escape');
            await modal.waitFor({ state: 'hidden' });
            await page.waitForFunction(() => document.activeElement?.hasAttribute('data-postpone-treatment'));
            await trigger.click();
            await modal.waitFor({ state: 'visible' });
            await modal.locator('textarea').fill('Elevated blood pressure at assessment.');
            await page.waitForFunction(() => getComputedStyle(document.getElementById('staffActionModal')).opacity === '1' && getComputedStyle(document.querySelector('#staffActionModal .modal-dialog')).transform === 'none');
            await page.screenshot({ path: path.join(os.tmpdir(), `treatment-postponement-${width}.png`), fullPage: false });
            await page.locator('#staffActionModalConfirm').click();
            await page.waitForFunction(() => window.qaToast?.includes('Treatment postponed'));
            assert.equal(submissions, 1, 'Exactly one request is sent');
            assert.deepEqual(errors, [], 'No JavaScript errors');
            console.log(`PASS: ${width}px dialog, required reason, Escape, focus restoration and submit.`);
            await page.close();
        }
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
