'use strict';
const assert = require('node:assert/strict');
const { chromium } = require('playwright');
const base = process.env.IDENTITY_TEST_BASE_URL || 'http://127.0.0.1:8094/Capstone%20System';

async function paste(field, value) {
    await field.evaluate((input, text) => {
        input.focus();
        input.setSelectionRange(0, input.value.length);
        const clipboard = new DataTransfer();
        clipboard.setData('text/plain', text);
        input.dispatchEvent(new ClipboardEvent('paste', { clipboardData: clipboard, bubbles: true, cancelable: true }));
    }, value);
}

async function main() {
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    try {
        for (const viewport of [{ width: 1365, height: 900 }, { width: 390, height: 844 }]) {
            const context = await browser.newContext({ viewport, reducedMotion: 'reduce' });
            const page = await context.newPage();
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.route('**/*', route => new URL(route.request().url()).hostname === '127.0.0.1'
                ? route.continue() : route.abort());
            await page.goto(`${base}/apps/views/register.php`, { waitUntil: 'domcontentloaded' });
            // Instant scrolling keeps narrow-viewport automation independent of
            // the site's animated scroll preference.
            await page.addStyleTag({ content: 'html { scroll-behavior: auto !important; }' });
            const first = page.locator('#regFirstName');
            await first.fill('Ana123! María');
            assert.equal(await first.inputValue(), 'Ana María');
            await first.fill('Peña');
            assert.equal(await first.evaluate(field => field.checkValidity()), true);
            await page.locator('#regLastName').fill('Dela Cruz');
            await page.locator('#regBirthdate').fill('2000-01-01');
            await page.locator('#regGender').selectOption('Prefer not to say');
            const next = page.getByRole('button', { name: 'Continue to account details' });
            await next.focus();
            await page.keyboard.press('Enter');
            const phone = page.locator('#regPhoneNumber');
            await paste(phone, '09abc123456789012345');
            assert.equal(await phone.inputValue(), '09123456789');
            await phone.fill('0912345678');
            assert.equal(await phone.evaluate(field => field.checkValidity()), false);
            await phone.fill('09123456789');
            assert.equal(await phone.evaluate(field => field.checkValidity()), true);

            // Dynamic modules must receive rules even before the first focus event.
            await page.evaluate(() => {
                const module = document.createElement('section');
                module.id = 'identityTestModule';
                module.innerHTML = '<input name="guardian_name"><input name="guardian_contact">'
                    + '<input name="physician_contact"><input name="office_contact">'
                    + '<input data-field="gcash_account_number"><input name="home_address">'
                    + '<textarea name="reason_for_visit"></textarea>'
                    + '<textarea data-field="contact_phone"></textarea>';
                document.body.append(module);
            });
            const guardian = page.locator('#identityTestModule [name="guardian_contact"]');
            await paste(guardian, '09ABC123456789999');
            assert.equal(await guardian.inputValue(), '09123456789');
            assert.equal(await guardian.getAttribute('inputmode'), 'numeric');
            await page.locator('#identityTestModule [name="guardian_name"]').fill('Maria45! Santos');
            assert.equal(await page.locator('#identityTestModule [name="guardian_name"]').inputValue(), 'Maria Santos');
            for (const selector of ['[name="office_contact"]', '[name="physician_contact"]', '[data-field="gcash_account_number"]']) {
                const field = page.locator(`#identityTestModule ${selector}`);
                await paste(field, '09abc123456789999');
                assert.equal(await field.inputValue(), '09123456789');
                await field.fill('0912');
                assert.equal(await field.evaluate(input => input.checkValidity()), false);
            }
            await page.locator('#identityTestModule [name="home_address"]').fill('Unit 2, Street 123');
            assert.equal(await page.locator('#identityTestModule [name="home_address"]').inputValue(), 'Unit 2, Street 123');
            await page.locator('#identityTestModule [name="reason_for_visit"]').fill('Pain in tooth 13.');
            assert.equal(await page.locator('#identityTestModule [name="reason_for_visit"]').inputValue(), 'Pain in tooth 13.');
            const clinicPhones = page.locator('#identityTestModule [data-field="contact_phone"]');
            await paste(clinicPhones, '09123456789\n09987abc654321');
            assert.equal(await clinicPhones.inputValue(), '09123456789\n09987654321');
            assert.equal(await clinicPhones.evaluate(field => field.checkValidity()), true);
            await clinicPhones.fill('0912');
            assert.equal(await clinicPhones.evaluate(field => field.checkValidity()), false);
            assert.deepEqual(errors, []);
            await context.close();
            console.log(`PASS: registration and dynamic input rules at ${viewport.width}px.`);
        }
    } finally {
        await browser.close();
    }
}
main().catch(error => { console.error(error); process.exitCode = 1; });
