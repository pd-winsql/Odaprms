'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright');

const resultDir = path.join(os.tmpdir(), 'codex-patient-account-page');
fs.mkdirSync(resultDir, { recursive: true });
const css = ['bootstrap.min.css', 'styles.css', 'dashboard.css', 'patient-dashboard.css', 'ui-refinements.css']
    .map(file => fs.readFileSync(`public/css/${file}`, 'utf8')).join('\n');
const identity = fs.readFileSync('public/js/identity-input.js', 'utf8');

async function main() {
    const html = execFileSync('C:\\xampp\\php\\php.exe', ['-d', `session.save_path=${resultDir}`, 'tests/renderPatientAccountPage.php'], { encoding: 'utf8' });
    assert(!html.includes('Warning:') && !html.includes('Fatal error:'));
    for (const viewport of [{ width: 1365, height: 900 }, { width: 390, height: 844 }]) {
        const browser = await chromium.launch({ channel: 'msedge', headless: true });
        try {
            const context = await browser.newContext({ viewport, reducedMotion: 'reduce' });
            const page = await context.newPage();
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.route('**/*Controller.php', async route => {
                const body = route.request().postData() || '';
                let result = { success: true, message: 'Account details saved.', display_name: 'Updated Patient' };
                if (body.includes('requestEmailChange')) result = { success: true, message: 'Verification code sent.', pending_email: 'new-patient@example.invalid' };
                if (body.includes('verifyEmailChange')) result = { success: true, message: 'Email updated.', email: 'new-patient@example.invalid' };
                if (body.includes('changePassword')) result = { success: true, message: 'Password changed successfully.' };
                await route.fulfill({ json: result });
            });
            await page.setContent(`<!doctype html><html><head><style>${css}</style><style>body{margin:0;padding:20px;background:#e7dfcf}*{box-sizing:border-box}.vd-my-account{margin:auto}html{scroll-behavior:auto!important}</style></head><body class="vd-dash-body"><script>window.vdAppUrl=p=>'http://127.0.0.1/'+p;window.LoadingUI={setButton:(b,on)=>{b.disabled=on}};</script>${html}<script>${identity}</script></body></html>`);

            const visibleText = (await page.locator('body').innerText()).toLowerCase();
            ['developer', 'database', 'controller', 'implementation', 'patients.email', 'users.email'].forEach(word => assert(!visibleText.includes(word), `No ${word} note is shown`));
            assert.equal(await page.locator('#accountPasswordTitle').count(), 0);
            assert.equal(await page.locator('.vd-pw-toggle span').first().isVisible(), false);
            assert.equal(await page.locator('.vd-pw-toggle i').first().evaluate(e => getComputedStyle(e).fontSize), '20px');
            assert.equal(await page.locator('#myAccountEmailPassword').evaluate(e => getComputedStyle(e).fontSize), '20px');
            assert.equal(await page.locator('#myAccountSave').isDisabled(), true);
            assert.equal(await page.locator('#myAccountSave').evaluate(el => getComputedStyle(el).opacity), '1');
            await page.locator('#myAccountFirst').fill('Patient22');
            assert.equal(await page.locator('#myAccountFirst').inputValue(), 'Patient');
            await page.locator('#myAccountPhone').fill('0912345678999');
            assert.equal(await page.locator('#myAccountPhone').inputValue(), '09123456789');
            await page.locator('#myAccountPhone').fill('09ab');
            assert.equal(await page.locator('#myAccountPhone').inputValue(), '09');
            await page.locator('#myAccountPhone').fill('09123456789');
            await page.locator('#myAccountFirst').fill('Updated');
            await page.locator('#myAccountSave').click();
            await page.waitForFunction(() => document.querySelector('#myAccountMessage').textContent === 'Account details saved.');

            await page.locator('#myAccountEmail').fill('new-patient@example.invalid');
            await page.locator('#myAccountEmailPassword').fill('TestPassword123');
            await page.locator('[data-toggle-my-account-password]').click();
            assert.equal(await page.locator('#myAccountEmailPassword').getAttribute('type'), 'text');
            assert.equal(await page.locator('[data-toggle-my-account-password]').getAttribute('aria-label'), 'Hide current password');
            await page.locator('[data-toggle-my-account-password]').click();
            assert.equal(await page.locator('#myAccountEmailPassword').getAttribute('type'), 'password');
            await page.locator('#myAccountSendCode').click();
            await page.waitForFunction(() => !document.querySelector('#myAccountVerification').hidden);
            await page.locator('#myAccountCode').fill('123456');
            await page.locator('#myAccountVerify').click();
            await page.waitForFunction(() => document.querySelector('#myAccountCurrentEmail').textContent === 'new-patient@example.invalid');

            await page.locator('#myAccountPasswordSection summary').click();
            await page.locator('#accountCurrentPw').fill('TestPassword123');
            await page.locator('#accountNewPw').fill('UpdatedPassword123');
            await page.locator('#accountConfirmPw').fill('UpdatedPassword123');
            assert.equal(await page.locator('#accountChangePasswordForm button[type=submit]').isDisabled(), true, 'Special character is required');
            await page.locator('#accountNewPw').fill('UpdatedPassword123!');
            await page.locator('#accountConfirmPw').fill('UpdatedPassword123!');
            await page.locator('#accountChangePasswordForm button[type=submit]').click();
            await page.waitForFunction(() => document.querySelector('#accountCurrentPw').value === '');
            assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'No horizontal overflow');
            assert.deepEqual(errors, []);
            await page.screenshot({ path: path.join(resultDir, `patient-${viewport.width}.png`), fullPage: true });
            console.log(`PASS: Patient My Account is concise, validated, responsive, and functional at ${viewport.width}px.`);
            await context.close();
        } finally {
            await browser.close();
        }
    }
}

main().catch(error => { console.error(error); process.exitCode = 1; });
