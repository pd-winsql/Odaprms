const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright');

// Actual PHP queue markup, isolated from live data and all database mutations.
const render = role => execFileSync('C:/xampp/php/php.exe', ['tests/adminQueueResponsiveFixture.php', role], { encoding: 'utf8' });
(async () => {
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    const outputDir = process.env.TEMP || require('node:os').tmpdir();
    try {
        for (const width of [1920, 1440, 1217, 1190, 719, 600, 368]) {
            const page = await browser.newPage({ viewport: { width, height: 950 } });
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.route('**/*', route => route.abort());
            // Include the real sidebar offset on desktop: container queries must
            // respond even when the viewport itself exceeds the breakpoint.
            await page.setContent(`<body class="vd-dash-body"><main class="vd-dash-main"><div class="vd-dash-content">${render('admin')}</div></main></body>`);
            for (const file of ['bootstrap.min.css', 'styles.css', 'dashboard.css', 'ui-refinements.css']) {
                await page.addStyleTag({ content: fs.readFileSync('public/css/' + file, 'utf8') });
            }
            await page.addStyleTag({ content: '@media(min-width: 992px) { .vd-dash-main { margin-left: 290px; width: calc(100% - 290px); } }' });
            await page.addScriptTag({ content: fs.readFileSync('public/js/dashboard-tables.js', 'utf8') });
            const table = page.locator('#todayLogbookTable');
            const row = table.locator('tbody tr').first();
            await page.waitForFunction(() => document.querySelector('.vd-table-pagination'));
            assert.equal(await table.locator('thead th').count(), 5, 'No redundant generated Details column');
            assert.equal(await table.locator('tbody tr:visible').count(), 10, 'Pagination still limits rows');
            const metrics = await table.evaluate(el => ({
                tableWidth: el.scrollWidth, wrapWidth: el.parentElement.clientWidth,
                pageWidth: document.documentElement.scrollWidth,
                rowDisplay: getComputedStyle(el.querySelector('tbody tr')).display,
                containerWidth: el.closest('.vd-admin-queue').clientWidth - parseFloat(getComputedStyle(el.closest('.vd-admin-queue')).paddingLeft) - parseFloat(getComputedStyle(el.closest('.vd-admin-queue')).paddingRight),
            }));
            assert(metrics.tableWidth <= metrics.wrapWidth + 1, `No table overflow at ${width}: ${JSON.stringify(metrics)}`);
            assert(metrics.pageWidth <= width + 1, 'No page overflow');
            assert.equal(metrics.rowDisplay, metrics.containerWidth <= 1000 ? 'grid' : 'table-row');
            if (metrics.containerWidth <= 1000) {
                const identity = await row.locator('td').nth(1).boundingBox();
                const state = await row.locator('td').nth(3).boundingBox();
                if (metrics.containerWidth > 560) {
                    assert(state.x > identity.x + identity.width, 'Medium layout puts status/actions on the right');
                    assert(Math.abs(state.y - identity.y) < 2, 'Medium identity and state share a row');
                } else {
                    assert(state.y > identity.y + identity.height, 'Small layout stacks status below details');
                }
                assert(await row.locator('.vd-admin-queue-inline-indicator').isVisible(), 'Queue indicator is beside the name');
            } else {
                assert(!(await row.locator('.vd-admin-queue-inline-indicator').isVisible()), 'Wide table keeps one queue indicator');
            }
            assert(!(await row.innerText()).includes('Treatment is underway'), 'Redundant treatment description is removed');
            for (const selector of ['.vd-appt-name', '.vd-appt-meta', '.vd-queue-state', '.vd-admin-queue-complete', '.vd-admin-queue-details', '.vd-admin-queue-postpone']) {
                const element = row.locator(selector).first();
                assert(await element.isVisible(), `${selector} visible at ${width}`);
                const box = await element.boundingBox();
                assert(box.x >= 0 && box.x + box.width <= width + 1, `${selector} fits at ${width}`);
                if (selector.startsWith('.vd-admin-queue-')) assert(box.height >= 44, 'Touch target is at least 44px');
            }
            assert.equal(await row.locator('.vd-admin-queue-complete').getAttribute('href'), 'dashboard.php?complete_visit=1');
            assert.equal(await row.locator('[data-postpone-treatment]').getAttribute('data-postpone-treatment'), '1');
            await row.locator('.vd-admin-queue-details').focus();
            assert(await row.locator('.vd-admin-queue-details').evaluate(el => el === document.activeElement), 'Keyboard focus supported');
            await page.screenshot({ path: path.join(outputDir, `admin-queue-responsive-${width}.png`), fullPage: false });
            await page.locator('.vd-table-page-btn').last().click();
            assert.equal(await table.locator('tbody tr:visible').count(), 2, 'Next page works in stacked layout');
            assert((await table.locator('tbody tr:visible').first().innerText()).includes('Torres 11'));
            await page.locator('.vd-table-page-btn').first().click();
            assert.equal(await table.locator('tbody tr:visible').count(), 10, 'Previous restores first page');
            assert.deepEqual(errors, []);
            console.log(`PASS ${width}px: no overflow, patient details/actions visible, keyboard focus, pagination.`);
            await page.close();
        }
        const page = await browser.newPage({ viewport: { width: 368, height: 950 } });
        await page.setContent(`<body class="vd-dash-body">${render('assistant')}</body>`);
        for (const file of ['bootstrap.min.css', 'styles.css', 'dashboard.css', 'ui-refinements.css']) await page.addStyleTag({ content: fs.readFileSync('public/css/' + file, 'utf8') });
        await page.addScriptTag({ content: fs.readFileSync('public/js/dashboard-tables.js', 'utf8') });
        assert.equal(await page.locator('.vd-admin-queue').count(), 0);
        assert.equal(await page.locator('[data-postpone-treatment]').count(), 0);
        assert.equal(await page.locator('tbody tr').first().evaluate(el => getComputedStyle(el).display), 'table-row', 'DA queue layout stays unchanged');
        console.log('PASS: DA queue remains unchanged.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
