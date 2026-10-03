const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const { execFileSync } = require('node:child_process');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    try {
        for (const type of ['appointments', 'utilization']) {
            const html = execFileSync('C:/xampp/php/php.exe', ['tests/renderReportPrint.php', type], { encoding: 'utf8' });
            assert(!html.includes('database is unavailable'), 'Database rendering must succeed');
            const page = await browser.newPage({ viewport: { width: 1000, height: 1000 } });
            await page.setContent(`<base href="http://localhost/Capstone%20System/apps/views/admin/dashboard.php"><body class="vd-dash-body"><main class="vd-dash-main"><div class="vd-dash-content">${html}</div></main></body>`);
            for (const file of ['bootstrap.min.css', 'styles.css', 'dashboard.css', 'ui-refinements.css']) {
                await page.addStyleTag({ content: fs.readFileSync(`public/css/${file}`, 'utf8') });
            }
            assert(await page.locator('.vd-report-print-header').isHidden());
            assert(await page.locator('.vd-report-print-summary').isHidden());
            await page.emulateMedia({ media: 'print' });
            assert(await page.locator('.vd-report-print-header').isVisible());
            assert(await page.locator('.vd-report-summary').isHidden());
            assert(await page.locator('.vd-report-print-summary').isVisible());
            assert(await page.locator('.vd-report-results .vd-dash-card-header').isHidden());
            assert.equal(await page.locator('.vd-report-row-hidden:visible').count(), await page.locator('.vd-report-row-hidden').count());
            assert.equal(await page.locator('.vd-report-table thead').evaluate(el => getComputedStyle(el).display), 'table-header-group');
            assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
            await page.screenshot({ path: path.join(os.tmpdir(), `report-print-${type}.png`), fullPage: true });
            await page.pdf({ path: path.join(os.tmpdir(), `report-print-${type}.pdf`), preferCSSPageSize: true });
            console.log(`PASS: ${type} print header, totals, plain table, all rows and screen-only controls.`);
            await page.close();
        }
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
