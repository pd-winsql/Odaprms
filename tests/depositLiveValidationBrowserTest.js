'use strict';
const fs = require('node:fs');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');

async function main() {
    const source = fs.readFileSync('apps/views/patient/partials/billing-content.php', 'utf8');
    const script = source.slice(source.lastIndexOf('<script>') + 8, source.lastIndexOf('</script>'));
    const css = ['bootstrap.min.css', 'styles.css', 'dashboard.css', 'patient-dashboard.css', 'deposit-ocr.css']
        .map(file => fs.readFileSync(`public/css/${file}`, 'utf8')).join('\n');
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    try {
        for (const width of [1365, 390]) {
            const page = await browser.newPage({ viewport: { width, height: 844 } });
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.route('**/depositController.php', async route => {
                const body = route.request().postData() || '';
                if (body.includes('name="action"\r\n\r\nextract')) {
                    await route.fulfill({ json: { success: true, fields: { amount: '100', reference_number: '1111111111', transaction_at: '2026-10-01T10:00' }, missing: [], message: 'Receipt scanned.' } });
                    return;
                }
                const reference = /name="gcash_reference"\r\n\r\n([^\r]+)/.exec(body)?.[1];
                if (reference === '3333333333') await new Promise(resolve => setTimeout(resolve, 700));
                await route.fulfill({ json: { success: true, exists: ['1111111111', '3333333333'].includes(reference) } });
            });
            await page.setContent(`<html><head><style>${css}</style></head><body class="vd-dash-body">
                <form class="depositSubmissionForm" data-deposit-ocr-form data-ocr-endpoint="http://localhost/depositController.php" data-required-amount="200" style="max-width:500px;padding:16px">
                    <input name="csrf_token" type="hidden" value="test"><input name="appointment_id" type="hidden" value="1">
                    <input name="receipt" type="file" accept="image/png" required>
                    <input name="receipt_amount" type="number" min="0.01" step="0.01" required data-ocr-field><div data-amount-error hidden></div>
                    <input name="gcash_reference" pattern="[0-9]{10,20}" required data-ocr-field><div data-reference-error hidden></div>
                    <input name="gcash_transaction_at" type="datetime-local" required data-ocr-field>
                    <div data-ocr-status><span data-ocr-message></span></div><div class="depositError d-none"></div>
                    <button type="submit">Submit for Verification</button>
                </form></body></html>`);
            await page.addScriptTag({ path: 'public/js/deposit-ocr.js' });
            await page.addScriptTag({ content: script });
            const amount = page.locator('[name=receipt_amount]');
            const reference = page.locator('[name=gcash_reference]');
            const submit = page.locator('button[type=submit]');
            assert(await submit.isDisabled());
            await page.locator('[name=receipt]').setInputFiles({ name: 'receipt.png', mimeType: 'image/png', buffer: Buffer.from('fixture') });
            await page.waitForFunction(() => document.querySelector('[data-reference-error]').textContent.includes('already been used'));
            assert((await page.locator('[data-amount-error]').innerText()).includes('200.00'), 'OCR amount is validated');
            await amount.fill('200');
            assert.equal(await page.locator('[data-amount-error]').isVisible(), false);
            assert(await submit.isDisabled(), 'Duplicate reference prevents submission');
            await reference.fill('2222222222');
            assert(await submit.isDisabled(), 'Pending lookup prevents submission');
            await page.waitForFunction(() => !document.querySelector('button[type=submit]').disabled);
            await amount.fill('201');
            assert(await submit.isDisabled());
            assert(await page.locator('[data-amount-error]').isVisible());
            await amount.fill('200');
            assert.equal(await submit.isDisabled(), false);
            await reference.fill('3333333333');
            await page.waitForTimeout(450);
            await reference.fill('2222222222');
            await page.waitForTimeout(1000);
            assert.equal(await page.locator('[data-reference-error]').isVisible(), false, 'Stale duplicate response is ignored');
            assert.equal(await submit.isDisabled(), false);
            await reference.fill('short');
            assert(await submit.isDisabled());
            assert(await page.locator('[data-reference-error]').isVisible());
            assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
            assert.deepEqual(errors, []);
            console.log(`PASS: Live amount/reference checks, OCR, pending/stale requests and corrections at ${width}px.`);
            await page.close();
        }
    } finally { await browser.close(); }
}
main().catch(error => { console.error(error); process.exitCode = 1; });
