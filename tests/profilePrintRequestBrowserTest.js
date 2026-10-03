const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright');

(async () => {
    const browser = await chromium.launch({ channel: 'msedge', headless: true });
    const css = ['bootstrap.min.css','styles.css','dashboard.css','patient-dashboard.css','ui-refinements.css'].map(f=>fs.readFileSync('public/css/'+f,'utf8')).join('\n');
    const modalScripts = ['bootstrap.bundle.min.js','action-modal.js'].map(f=>fs.readFileSync('public/js/'+f,'utf8')).join('\n');
    try {
        for (const mode of ['patient','staff']) for (const width of [1365,390]) {
            const page=await browser.newPage({viewport:{width,height:900}});
            const errors=[]; page.on('pageerror',e=>errors.push(e.message));
            let posts=0;
            let requests=mode==='staff'?[{request_id:1,patient_id:2,patient_name:'Sample Patient',clinic_name:'Alcala Branch',purpose:'Personal copy',include_billing:1,requested_at:'2026-10-03 22:54:00',status:'Pending'}]:[];
            await page.route('**/profilePrintRequestController.php',async route=>{
                const body=route.request().postData()||'';
                if(route.request().method()==='POST') {
                    posts++;
                    if(body.includes('create')) requests=[{request_id:1,clinic_name:'Alcala Branch',include_billing:0,status:'Pending'}];
                    else requests[0].status=body.includes('Cancelled')?'Cancelled':body.includes('Collected')?'Collected':'Ready for pickup';
                }
                await route.fulfill({json:{success:true,requests}});
            });
            await page.route('**/ui-test',r=>r.fulfill({body:'<html></html>',contentType:'text/html'}));
            await page.goto('http://localhost/ui-test');
            const html=execFileSync('C:/xampp/php/php.exe',['tests/renderProfilePrintRequests.php',mode],{encoding:'utf8'});
            assert(!html.includes('Warning:')&&!html.includes('Fatal error:'));
            await page.setContent(`<style>${css}</style><style>body.vd-dash-body{display:block;padding:24px;margin:0;background:#e7dfcf}main{max-width:1180px;margin:auto}*{box-sizing:border-box}</style><body class="vd-dash-body"><main>${html}</main><script>${modalScripts}</script></body>`);
            page.on('dialog',d=>{errors.push('Unexpected browser-native dialog');d.dismiss();});
            if(mode==='patient') {
                await page.waitForFunction(()=>!document.getElementById('openProfileRequest').disabled);
                await page.locator('#openProfileRequest').click();
                assert(await page.locator('#profileRequestDialog').isVisible());
                await page.locator('#requestPickupClinic').selectOption('1');
                await page.locator('#requestPurpose').selectOption('Other');
                assert(await page.locator('#requestReason').isVisible());
                assert(!(await page.locator('[name=include_billing]').isChecked()));
                await page.locator('#requestReason').fill('School requirement');
                await page.screenshot({path:path.join(os.tmpdir(),`profile-request-dialog-${width}.png`)});
                assert(await page.locator('#profileRequestDialog').evaluate(el=>el.getBoundingClientRect().width<=innerWidth-16));
                await page.locator('#profileRequestForm button[type=submit]').click();
                await page.locator('.vd-request-badge').filter({hasText:'Pending'}).waitFor();
                assert(await page.locator('#profileRequestDialog').isHidden());
                assert(await page.locator('#openProfileRequest').isDisabled());
                await page.screenshot({path:path.join(os.tmpdir(),`profile-request-patient-${width}.png`)});
                await page.getByRole('button',{name:'Cancel request',exact:true}).click();
                await page.locator('#staffActionModal').waitFor({state:'visible'});
                assert.equal(posts,1,'Opening confirmation must not submit');
                assert.equal(await page.locator('#staffActionModalCancel').textContent(),'Keep request');
                await page.waitForFunction(()=>document.getElementById('staffActionModal').contains(document.activeElement));
                await page.screenshot({path:path.join(os.tmpdir(),`profile-request-cancel-${width}.png`)});
                await page.locator('#staffActionModalCancel').click();
                await page.locator('#staffActionModal').waitFor({state:'hidden'});
                assert.equal(posts,1,'Keeping request must not submit');
                await page.getByRole('button',{name:'Cancel request',exact:true}).click();
                await page.locator('#staffActionModal').waitFor({state:'visible'});
                await page.waitForFunction(()=>document.getElementById('staffActionModal').contains(document.activeElement));
                await page.keyboard.press('Escape');
                await page.locator('#staffActionModal').waitFor({state:'hidden'});
                assert.equal(posts,1,'Escape must not submit');
                await page.getByRole('button',{name:'Cancel request',exact:true}).click();
                await page.locator('#staffActionModalConfirm').click();
                await page.locator('.vd-request-badge').filter({hasText:'Cancelled'}).waitFor();
                assert.equal(posts,2,'Confirm submits once');
                assert(!(await page.locator('#openProfileRequest').isDisabled()));
                await page.locator('#openProfileRequest').click();
                await page.keyboard.press('Escape');
                assert(await page.locator('#profileRequestDialog').isHidden());
            } else {
                await page.locator('[data-patient-tab=requests]').click();
                await page.locator('#profileRequestRows tr').waitFor();
                assert.equal(await page.locator('#profileRequestsPanel .vd-appt-table-wrap').evaluate(el=>getComputedStyle(el).borderTopWidth),'0px');
                assert.equal(await page.locator('#profileRequestsPanel .vd-appt-table-wrap').evaluate(el=>getComputedStyle(el).borderRadius),'0px');
                assert.notEqual(await page.locator('#profileRequestsUI').evaluate(el=>getComputedStyle(el).borderTopWidth),'0px');
                assert(await page.locator('#patientRecordsPanel').isHidden());
                assert.equal(await page.locator('[data-patient-tab=requests]').getAttribute('aria-selected'),'true');
                assert((await page.getByRole('link',{name:'View / Print'}).getAttribute('href')).includes('request_id=1'));
                await page.screenshot({path:path.join(os.tmpdir(),`profile-request-staff-${width}.png`)});
                await page.getByRole('button',{name:'Mark ready',exact:true}).click();
                await page.getByRole('button',{name:'Mark collected',exact:true}).waitFor();
                await page.getByRole('button',{name:'Mark collected',exact:true}).click();
                await page.locator('#staffActionModalCancel').click();
                await page.locator('#staffActionModal').waitFor({state:'hidden'});
                assert.equal(posts,1,'Go back keeps ready status');
                await page.getByRole('button',{name:'Mark collected',exact:true}).click();
                await page.locator('#staffActionModalConfirm').click();
                await page.locator('#profileRequestEmpty').waitFor();
                assert.equal(posts,2,'Confirm collection submits once');
                await page.locator('#profileRequestFilter').selectOption('all');
                await page.locator('.vd-request-badge').filter({hasText:'Collected'}).waitFor();
            }
            assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'No viewport overflow');
            assert.deepEqual(errors,[]);
            console.log(`PASS ${mode} ${width}px: layout and request controls`);
            await page.close();
        }
    } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
