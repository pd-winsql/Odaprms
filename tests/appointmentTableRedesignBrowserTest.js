const fs=require('node:fs');
const assert=require('node:assert/strict');
const os=require('node:os');
const path=require('node:path');
const {execFileSync}=require('node:child_process');
const {chromium}=require('playwright');
(async()=>{
 const html=execFileSync('C:/xampp/php/php.exe',['tests/renderAppointmentTable.php'],{encoding:'utf8'});
 assert(!html.includes('Warning:')&&!html.includes('Fatal error:'));
 const css=['bootstrap.min.css','styles.css','dashboard.css','ui-refinements.css'].map(f=>fs.readFileSync('public/css/'+f,'utf8')).join('\n');
 const scripts=['bootstrap.bundle.min.js','dashboard-tables.js'].map(f=>fs.readFileSync('public/js/'+f,'utf8')).join('\n');
 const browser=await chromium.launch({channel:'msedge',headless:true});
 try{for(const width of [1623,1134,768,390,320]){
  const page=await browser.newPage({viewport:{width,height:1000}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('**/test-page',r=>r.fulfill({body:'<html></html>'}));await page.goto('http://localhost/test-page');
  await page.setContent(`<style>${css}</style><style>body.vd-dash-body{display:block;padding:20px}main{margin:auto;max-width:1250px}</style><body class="vd-dash-body"><main class="vd-dash-content"><script>window.vdAppUrl=p=>'http://localhost/'+p;</script>${html}</main><script>${scripts}</script></body>`);
  const table=page.locator('#upcomingApptTable');
  assert.equal(await page.locator('.vd-status-toggle-btn').count(),0);
  assert(await table.locator('tbody tr').first().locator('td').first().isVisible());
  assert(await table.locator('.vd-activity-avatar').first().isHidden());
  assert(await table.locator('.vd-role-chip').isHidden());
  assert.equal(await table.locator('.vd-status').first().innerText(),'Pending review');
  await page.locator('#upcomingMoreStatus').selectOption('Confirmed');
  assert.equal(await table.locator('tbody tr:visible').count(),1);
  await page.locator('#clearUpcomingFilters').click();
  assert.equal(await table.locator('tbody tr:visible').count(),2);
  await page.locator('#filterDateFromUpcoming').fill('2026-10-19');await page.locator('#filterDateFromUpcoming').dispatchEvent('change');
  assert.equal(await table.locator('tbody tr:visible').count(),1);
  await page.locator('#clearUpcomingFilters').click();
  if(width<1200){await table.locator('summary').first().click();assert(await table.locator('.vd-compact-row-details div').first().isVisible());}
  if(width<1200){assert(await table.locator('thead th').nth(1).isHidden());assert(await table.locator('thead th').nth(4).isHidden());}
  if(width<576) assert(await table.locator('thead th').nth(2).isHidden());
  assert(await table.evaluate(t=>t.scrollWidth<=t.parentElement.clientWidth+1),'Table must fit its container');
  assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Page must not overflow');
  await page.screenshot({path:path.join(os.tmpdir(),`appointment-redesign-${width}.png`)});
  await table.locator('.vd-appt-action-toggle').first().click();
  const detailsButton=page.locator('.vd-appt-action-dropdown.show .vd-appointment-details-btn');await detailsButton.click();
  await page.locator('#appointmentDetailsModal').waitFor({state:'visible'});
  assert((await page.locator('#appointmentDetailsTitle').textContent()).includes('Sample'));
  assert.deepEqual(errors,[]);
  console.log(`PASS ${width}px: filters, patient visibility, responsive details and action menu`);
  await page.close();
 }}finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
