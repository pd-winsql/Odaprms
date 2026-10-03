'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright');
const resultDir = path.resolve('test-results/account-page');
fs.mkdirSync(resultDir, { recursive: true });
const css = ['bootstrap.min.css', 'styles.css', 'dashboard.css', 'ui-refinements.css'].map(file => fs.readFileSync(`public/css/${file}`, 'utf8')).join('\n');
const identity = fs.readFileSync('public/js/identity-input.js', 'utf8');
async function main() {
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    try {
        for (const role of ['Admin', 'Dental Assistant']) {
            for (const viewport of [{ width: 1365, height: 900 }, { width: 390, height: 844 }]) {
                const html = execFileSync('C:\\xampp\\php\\php.exe', ['-d', `session.save_path=${resultDir}`, 'tests/renderStaffAccountPage.php', role, 'legacy'], { encoding: 'utf8' });
                assert(!html.includes('Warning:') && !html.includes('Fatal error:'));
                const context = await browser.newContext({ viewport, reducedMotion: 'reduce' });
                const page = await context.newPage();
                const errors = [];
                page.on('pageerror', e => errors.push(e.message));
                await page.route('**/*', async route => {
                    const request = route.request();
                    if (!request.url().includes('Controller.php')) return route.abort();
                    const body = request.postData() || '';
                    let result = { success: true, message: 'Account details saved.', display_name: 'Updated Account' };
                    if (body.includes('requestEmailChange')) result = { success: true, message: 'Verification code sent.', pending_email: 'new-account@example.invalid' };
                    if (body.includes('verifyEmailChange')) result = { success: true, message: 'Email updated.', email: 'new-account@example.invalid' };
                    if (body.includes('changePassword')) result = { success: true, message: 'Password changed successfully.' };
                    await route.fulfill({ json: result });
                });
                await page.setContent(`<!doctype html><html><head><style>${css}</style><style>body{margin:0;padding:20px;background:#e7dfcf}*{box-sizing:border-box}.vd-my-account{margin:auto}html{scroll-behavior:auto!important}</style></head><body class="vd-dash-body"><script>window.vdAppUrl=p=>'http://127.0.0.1/'+p;window.LoadingUI={setButton:(b,on)=>{b.disabled=on}};</script>${html}<script>${identity}</script></body></html>`);
                assert.equal(await page.locator('#accountPasswordTitle').count(), 0);
                assert.equal(await page.locator('.vd-pw-toggle span').first().isVisible(), false);
                assert.equal(await page.locator('.vd-pw-toggle i').first().evaluate(e => getComputedStyle(e).fontSize), '20px');
                assert.equal(await page.locator('#myAccountEmailPassword').evaluate(e => getComputedStyle(e).fontSize), '20px');
                assert.equal(await page.locator('#myAccountSave').isDisabled(), true);
                assert.equal(await page.locator('#myAccountSave').evaluate(e => getComputedStyle(e).opacity), '1');
                assert.equal(await page.locator('#myAccountPasswordSection').evaluate(e => e.open), true);
                await page.locator('#myAccountFirst').fill('Updated');
                assert.equal(await page.locator('#myAccountSave').isDisabled(), false);
                await page.locator('#myAccountSave').click();
                await page.waitForFunction(() => document.querySelector('#myAccountMessage').textContent === 'Account details saved.');
                assert.equal(await page.locator('#myAccountSave').isDisabled(), true);
                await page.locator('#myAccountEmail').fill('new-account@example.invalid');
                await page.locator('#myAccountEmailPassword').fill('TestPassword123');
                await page.locator('[data-toggle-my-account-password]').click();
                assert.equal(await page.locator('#myAccountEmailPassword').getAttribute('type'), 'text');
                assert.equal(await page.locator('[data-toggle-my-account-password]').getAttribute('aria-label'), 'Hide current password');
                await page.locator('[data-toggle-my-account-password]').click();
                assert.equal(await page.locator('#myAccountEmailPassword').getAttribute('type'), 'password');
                await page.locator('#myAccountSendCode').click();
                await page.waitForFunction(() => !document.querySelector('#myAccountVerification').hidden);
                assert.equal(await page.locator('#myAccountSendCode').isDisabled(), true);
                await page.locator('#myAccountCode').fill('123456');
                await page.locator('#myAccountVerify').click();
                await page.waitForFunction(() => document.querySelector('#myAccountCurrentEmail').textContent === 'new-account@example.invalid');
                assert.equal(await page.locator('#myAccountVerification').isVisible(), false);
                const pw = page.locator('#accountChangePasswordForm button[type=submit]');
                assert.equal(await pw.isDisabled(), true);
                await page.locator('#accountCurrentPw').fill('TestPassword123');
                await page.locator('#accountNewPw').fill('UpdatedPassword123');
                await page.locator('#accountConfirmPw').fill('UpdatedPassword123');
                assert.equal(await pw.isDisabled(), false);
                await pw.click();
                await page.waitForFunction(() => document.querySelector('#accountCurrentPw').value === '');
                assert.equal(await pw.isDisabled(), true);
                assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'No horizontal overflow');
                await page.screenshot({ path: path.join(resultDir, `${role.replaceAll(' ', '-')}-${viewport.width}.png`), fullPage: true });
                assert.deepEqual(errors, []);
                console.log(`PASS: ${role} account sections, independent saves, verification, legacy links and opaque buttons at ${viewport.width}px.`);
                await context.close();
            }
        }
    } finally { await browser.close(); }
}
main().catch(error => { console.error(error); process.exitCode = 1; });
