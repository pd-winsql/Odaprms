const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright');
(async () => {
    const html = execFileSync('C:/xampp/php/php.exe', ['tests/adminQueueResponsiveFixture.php'], { encoding: 'utf8' });
    const modalHtml = fs.readFileSync('apps/views/shared/treatment-postponement-modal.php', 'utf8');
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    try {
        for (const width of [1440, 390]) {
            const height = width < 500 ? 660 : 950;
            const page = await browser.newPage({ viewport: { width, height } });
            const errors = []; page.on('pageerror', e => errors.push(e.message));
            let submissions = 0, taken = false, empty = false, failNext = true;
            const schedule = (id, slots) => ({ schedule_id: id, clinic_name: 'Tuguegarao Branch', sched_date: `2027-04-0${id - 100}`, start_time: '08:00:00', end_time: '17:00:00', available_slots: slots });
            await page.route('**/*', route => route.abort());
            await page.route('**/treatmentPostponementController.php*', async route => {
                if (route.request().method() === 'GET') {
                    await route.fulfill({ json: { success: true, schedules: empty ? [] : [schedule(101,0), schedule(102,taken ? 0 : 3), schedule(103,1)] } });
                    return;
                }
                submissions++;
                const body = route.request().postData();
                assert(body.includes('Elevated blood pressure'));
                assert(body.includes('qa-token'));
                if (failNext) {
                    assert(body.includes('reschedule') && body.includes('102'));
                    failNext = false; taken = true;
                    await route.fulfill({ json: { success: false, code: 'schedule_full', message: 'That schedule is full. Choose another date or Book later.' } });
                } else {
                    if (empty) assert(body.includes('book_later'));
                    else assert(body.includes('reschedule') && body.includes('103'));
                    await route.fulfill({ json: { success: true, message: 'Treatment postponed and replacement appointment scheduled.' } });
                }
            });
            await page.setContent(`<body class="vd-dash-body"><div class="vd-dash-content">${html}</div>${modalHtml}</body>`);
            for (const file of ['bootstrap.min.css','styles.css','dashboard.css','ui-refinements.css']) await page.addStyleTag({ content: fs.readFileSync('public/css/' + file, 'utf8') });
            await page.addScriptTag({ content: fs.readFileSync('public/js/bootstrap.bundle.min.js', 'utf8') });
            await page.evaluate(() => { window.vdAppUrl = value => 'http://localhost/Capstone%20System/' + value; window.showToast = message => { window.qaToast = message; }; });
            await page.addScriptTag({ content: fs.readFileSync('public/js/treatment-postponement.js', 'utf8') });
            const trigger = page.locator('[data-postpone-treatment]').first();
            const modal = page.locator('#treatmentPostponementModal');
            const confirm = page.locator('#postponementConfirm');
            await trigger.click();
            await page.locator('[data-schedule-id="102"]').waitFor();
            assert(await page.locator('[data-schedule-id="101"]').isDisabled(), 'Full schedule is unselectable');
            assert((await page.locator('[data-schedule-id="102"]').innerText()).includes('3 slots available'));
            assert(await confirm.isDisabled(), 'Schedule must be selected');
            await page.locator('[data-schedule-id="102"]').click();
            await confirm.click();
            assert.equal(submissions,0,'Reason required');
            await page.locator('#postponementReason').fill('Elevated blood pressure at assessment.');
            await confirm.click();
            await page.locator('#postponementError').waitFor({state:'visible'});
            assert(await modal.isVisible(), 'Failed save stays in the dialog');
            assert.equal(await page.locator('#postponementReason').inputValue(),'Elevated blood pressure at assessment.');
            assert(await page.locator('[data-schedule-id="102"]').isDisabled(), 'Refreshed full date disabled');
            assert(await confirm.isDisabled(),'Stale selection cleared');
            await page.locator('[data-schedule-id="103"]').click();
            const bounds = await modal.locator('.modal-dialog').boundingBox();
            assert(bounds.x >= 0 && bounds.x + bounds.width <= width + 1, 'Dialog fits viewport');
            assert(bounds.y >= 0 && bounds.y + bounds.height <= height + 1, 'Dialog fits viewport height');
            await page.waitForFunction(() => getComputedStyle(document.querySelector('#treatmentPostponementModal .modal-dialog')).transform === 'none');
            await page.screenshot({ path: path.join(os.tmpdir(), `treatment-postponement-reschedule-${width}.png`), fullPage: false, animations: 'disabled' });
            await page.keyboard.press('Escape');
            await modal.waitFor({state:'hidden'});
            await page.waitForFunction(() => document.activeElement?.hasAttribute('data-postpone-treatment'));
            assert(await trigger.evaluate(el => el === document.activeElement),'Escape restores focus');
            await trigger.click();
            await page.locator('[data-schedule-id="103"]').waitFor();
            await page.locator('#postponementReason').fill('Elevated blood pressure at assessment.');
            await page.locator('[data-schedule-id="103"]').click();
            await confirm.click();
            await modal.waitFor({state:'hidden'});
            assert.equal(submissions,2,'One successful replacement save');
            empty = true;
            await trigger.click();
            await page.waitForFunction(() => document.getElementById('postponementSchedules').textContent.includes('No eligible schedules'));
            assert(await confirm.isDisabled(),'Empty schedules cannot reschedule');
            await page.locator('input[value="book_later"]').check();
            assert.equal(await confirm.textContent(),'Postpone treatment');
            assert(!(await page.locator('#postponementSchedulesPanel').isVisible()));
            await page.locator('#postponementReason').fill('Elevated blood pressure at assessment.');
            await confirm.click();
            await modal.waitFor({state:'hidden'});
            assert.equal(submissions,3,'Book later can save without a schedule');
            assert.deepEqual(errors,[]);
            console.log(`PASS ${width}px: full/available/empty schedules, required reason, stale-slot recovery, reschedule, Book later, Escape and focus.`);
            await page.close();
        }
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
