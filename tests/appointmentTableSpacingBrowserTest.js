const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { chromium } = require('playwright');
(async () => {
    const browser = await chromium.launch({channel:'msedge',headless:true});
    try {
        const css=['bootstrap.min.css','styles.css','dashboard.css','ui-refinements.css'].map(f=>fs.readFileSync('public/css/'+f,'utf8')).join('\n');
        for(const width of [1365,390]) {
          for (const bodyClass of ['vd-appointment-table-body','vd-flush-table-body']) {
            const page=await browser.newPage({viewport:{width,height:850}});
            await page.setContent(`<style>${css}</style><style>body.vd-dash-body{display:block;padding:20px}main{max-width:1100px;margin:auto}</style><body class="vd-dash-body"><main><section class="vd-dash-card"><div class="vd-dash-card-header">Upcoming appointments</div><div class="vd-filter-bar">Date filters</div><div class="vd-dash-card-body vd-appointment-table-body"><div class="vd-table-frame"><div class="vd-appt-table-wrap"><table class="vd-appt-table w-100"><thead><tr><th>Patient</th><th>Schedule</th><th>Status</th></tr></thead><tbody><tr><td>Sample Patient</td><td>Oct 10, 2026</td><td>Confirmed</td></tr></tbody></table></div></div><nav class="vd-table-pagination"><span>Showing 1–1 of 1</span><span>Page 1 of 1</span></nav></div></section></main></body>`);
            await page.locator('.vd-dash-card-body').evaluate((el, name)=>el.className='vd-dash-card-body '+name,bodyClass);
            const spacing=await page.locator('.vd-dash-card-body').evaluate(el=>({padding:getComputedStyle(el).padding,bodyLeft:el.getBoundingClientRect().left,tableLeft:el.querySelector('.vd-table-frame').getBoundingClientRect().left,bodyRight:el.getBoundingClientRect().right,tableRight:el.querySelector('.vd-table-frame').getBoundingClientRect().right}));
            assert.equal(spacing.padding,'0px');
            assert.equal(spacing.bodyLeft,spacing.tableLeft);
            assert.equal(spacing.bodyRight,spacing.tableRight);
            assert.equal(await page.locator('td').first().evaluate(el=>getComputedStyle(el).paddingLeft),width<768?'14px':'22px');
            assert.equal(await page.locator('.vd-table-pagination').evaluate(el=>getComputedStyle(el).paddingLeft),width<768?'14px':'22px');
            assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
            await page.screenshot({path:path.join(os.tmpdir(),`appointment-table-spacing-${width}.png`)});
            console.log(`PASS ${bodyClass} ${width}px: flush table, padded cells and footer, no page overflow`);
            await page.close();
          }
        }
    } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
