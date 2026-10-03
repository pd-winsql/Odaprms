'use strict';
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');

async function main() {
    const source = fs.readFileSync('apps/views/patient/partials/booking-content.php', 'utf8');
    const functions = source.slice(source.indexOf('    function parseLocalDate('), source.indexOf('    async function refreshSchedulesForActiveClinic('));
    const css = ['bootstrap.min.css', 'styles.css', 'dashboard.css', 'patient-dashboard.css', 'ui-refinements.css']
        .map(file => fs.readFileSync(`public/css/${file}`, 'utf8')).join('\n');
    const schedules = [
        { schedule_id: 1, sched_date: '2026-10-10', available_slots: 0 },
        { schedule_id: 2, sched_date: '2026-10-12', available_slots: 10, has_booking_conflict: true },
        { schedule_id: 3, sched_date: '2026-10-14', available_slots: 15 },
        { schedule_id: 4, sched_date: '2026-10-20', available_slots: 8 },
        { schedule_id: 5, sched_date: '2026-10-20', available_slots: 6 },
        { schedule_id: 6, sched_date: '2026-12-02', available_slots: 9 },
    ].map(item => ({ start_time: '07:00:00', end_time: '17:00:00', ...item }));
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    try {
        for (const width of [1365, 390, 320]) {
            const page = await browser.newPage({ viewport: { width, height: 900 } });
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.setContent(`<html><head><style>${css}</style><style>body.vd-dash-body{display:block;padding:12px}.vd-booking-calendar{max-width:1100px;margin:auto;background:#fffdf9;border-radius:16px}</style></head><body class="vd-dash-body">
                <button class="vd-clinic-switch-btn" data-clinic-id="1" data-clinic-name="Alcala Branch">Alcala</button>
                <button class="vd-clinic-switch-btn" data-clinic-id="2" data-clinic-name="Tuguegarao Branch">Tuguegarao</button>
                <span id="bookingClinicLabel"></span><span id="bookingSelectedDate"></span>
                <input id="dashboardClinicInput" type="hidden"><input id="dashboardScheduleInput" type="hidden">
                <div class="vd-booking-calendar" id="bookingScheduleGrid"></div>
                <div id="bookingScheduleEmpty">No schedules available.</div><button id="bookingNext">Continue</button>
                </body></html>`);
            await page.addScriptTag({ content: `
                const schedulesByClinic = ${JSON.stringify({ 1: schedules, 2: [] })};
                const clinicButtons = Array.from(document.querySelectorAll('.vd-clinic-switch-btn'));
                const grid = document.getElementById('bookingScheduleGrid'), empty = document.getElementById('bookingScheduleEmpty');
                const clinicInput = document.getElementById('dashboardClinicInput'), scheduleInput = document.getElementById('dashboardScheduleInput');
                const clinicLabel = document.getElementById('bookingClinicLabel'), selectedDate = document.getElementById('bookingSelectedDate');
                const nextButton = document.getElementById('bookingNext');
                const earliestCalendarDate = '2026-10-10', emptyScheduleMessage = empty.textContent;
                let calendarMonth = '', calendarClinicId = '', selectedSchedule = null, furthestStep = 1;
                function updateActionSummary() { nextButton.disabled = !scheduleInput.value; }
                ${functions}
                window.testRender = (clinic, preferred = '') => renderSchedules(clinicButtons[clinic - 1], preferred);
                window.testConflict = () => { schedulesByClinic[1][2].has_booking_conflict = true; testRender(1, '3'); };
                testRender(1);
            ` });
            const day = label => page.getByRole('button', { name: label, exact: true });
            assert.equal(await page.locator('.vd-booking-calendar-day').count(), 31);
            assert(await day('October 10, 2026, fully booked').isDisabled());
            assert(await day('October 12, 2026, already booked on this date').isDisabled());
            assert(await day('October 9, 2026, unavailable').isDisabled());
            assert(await page.locator('#bookingNext').isDisabled());
            await day('October 14, 2026, available').click();
            assert.equal(await page.locator('#dashboardScheduleInput').inputValue(), '3');
            assert.equal(await page.locator('#bookingNext').isDisabled(), false);
            await page.evaluate(() => testRender(1, '3'));
            assert.equal(await page.locator('#dashboardScheduleInput').inputValue(), '3', 'Refresh preserves a usable schedule');
            await day('October 20, 2026, available').click();
            assert(await page.locator('#bookingNext').isDisabled(), 'Multiple windows require an explicit choice');
            assert.equal(await page.locator('.vd-booking-calendar-window').count(), 2);
            await page.locator('.vd-booking-calendar-window').last().click();
            assert.equal(await page.locator('#dashboardScheduleInput').inputValue(), '5');
            await page.locator('[data-calendar-next]').click();
            assert(await page.locator('#bookingNext').isDisabled());
            assert((await page.locator('.vd-booking-calendar-detail').innerText()).includes('No schedules are available this month.'));
            await page.locator('[data-calendar-next]').click();
            await day('December 2, 2026, available').click();
            assert.equal(await page.locator('#dashboardScheduleInput').inputValue(), '6');
            await page.evaluate(() => testConflict());
            assert.equal(await page.locator('#dashboardScheduleInput').inputValue(), '', 'Refresh clears a conflicting schedule');
            await page.evaluate(() => testRender(2));
            assert(await page.locator('#bookingScheduleGrid').isHidden());
            assert(await page.locator('#bookingScheduleEmpty').isVisible());
            await page.evaluate(() => testRender(1));
            await day('October 20, 2026, available').click();
            await page.locator('.vd-booking-calendar-window').first().click();
            assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
            assert.deepEqual(errors, []);
            await page.screenshot({ path: path.join(os.tmpdir(), `booking-calendar-${width}.png`), fullPage: true });
            console.log(`PASS: Calendar states, windows, navigation, refresh and clinic changes at ${width}px.`);
            await page.close();
        }
    } finally { await browser.close(); }
}
main().catch(error => { console.error(error); process.exitCode = 1; });
