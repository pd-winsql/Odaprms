const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const {chromium} = require('playwright');
(async () => {
    const controls=execFileSync('C:/xampp/php/php.exe',['tests/patientDepositCreditFixture.php','--multiple'],{encoding:'utf8'});
    const source=fs.readFileSync('apps/views/patient/partials/billing-content.php','utf8');
    const script=source.slice(source.lastIndexOf('<script>')+8,source.lastIndexOf('</script>'));
    const browser=await chromium.launch({channel:'msedge',headless:true});
    try {
        for (const width of [1440,390]) {
            const page=await browser.newPage({viewport:{width,height:844}});
            const errors=[]; page.on('pageerror', e=>errors.push(e.message));
            let requests=0;
            await page.route('**/*', route=>route.abort());
            await page.route('**/depositController.php',async route=>{
                requests++;
                const body=route.request().postData();
                for(const value of ['applyCredit','101','202','qa-credit-token']) assert(body.includes(value));
                await new Promise(resolve=>setTimeout(resolve,200));
                await route.fulfill({json:requests===1
                    ? {success:false,message:'This deposit has already been transferred. Refresh your appointments.'}
                    : {success:true,message:'Deposit transferred and the replacement appointment confirmed.'}});
            });
            const modal=fs.readFileSync('apps/views/shared/staff-action-modal.php','utf8');
            await page.setContent(`<body class="vd-dash-body"><main class="vd-dash-main"><div class="vd-dash-content"><div class="vd-dash-card"><div class="vd-dash-card-body">${controls}</div></div><a href="#billing-content.php" data-page="billing-content.php">Deposit</a></div></main>${modal}</body>`);
            for (const file of ['bootstrap.min.css','styles.css','dashboard.css','patient-dashboard.css','ui-refinements.css']) await page.addStyleTag({content:fs.readFileSync('public/css/'+file,'utf8')});
            for (const file of ['bootstrap.bundle.min.js','action-modal.js']) await page.addScriptTag({content:fs.readFileSync('public/js/'+file,'utf8')});
            await page.evaluate(()=>{
                window.vdAppUrl=p=>'http://localhost/'+p;
                window.showToast=m=>window.qaToast=m;
                document.querySelector('[data-page]').addEventListener('click',()=>window.qaReloaded=true);
            });
            await page.addScriptTag({content:script});
            const trigger=page.locator('[data-patient-credit-form] button');
            await page.locator('[name="source_appointment_id"][value="102"]').check();
            assert(await page.locator('[name="source_appointment_id"][value="102"]').isChecked());
            await page.locator('[name="source_appointment_id"][value="101"]').check();
            await page.screenshot({path:path.join(os.tmpdir(),`patient-deposit-credit-options-${width}.png`)});
            await trigger.click();
            await page.locator('#staffActionModal.show').waitFor();
            await page.waitForFunction(()=>document.activeElement===document.querySelector('#staffActionModalConfirm'));
            assert((await page.locator('#staffActionModalDetails').innerText()).includes('#202'));
            await page.screenshot({path:path.join(os.tmpdir(),`patient-deposit-credit-${width}.png`)});
            await page.keyboard.press('Escape');
            await page.waitForFunction(()=>document.activeElement===document.querySelector('[data-patient-credit-form] button'));
            assert.equal(requests,0,'Escape does not transfer payment');
            await trigger.click();
            await page.waitForFunction(()=>document.activeElement===document.querySelector('#staffActionModalConfirm'));
            await page.locator('#staffActionModalConfirm').click();
            await page.locator('[data-credit-error]:not(.d-none)').waitFor();
            assert((await page.locator('[data-credit-error]').innerText()).includes('already been transferred'));
            assert.equal(await trigger.isDisabled(),false,'Failure allows retry');
            await trigger.click();
            await page.waitForFunction(()=>document.activeElement===document.querySelector('#staffActionModalConfirm'));
            await page.locator('#staffActionModalConfirm').click();
            await page.waitForFunction(()=>window.qaReloaded===true);
            assert.equal(requests,2);
            assert.deepEqual(errors,[]);
            const overflow=await page.evaluate(()=>({width:innerWidth,scroll:document.documentElement.scrollWidth,items:[...document.querySelectorAll('body *')].filter(e=>e.getBoundingClientRect().right>innerWidth+1).map(e=>[e.tagName,e.className,e.getBoundingClientRect().right])}));
            assert(overflow.scroll<=overflow.width,JSON.stringify(overflow));
            console.log(`PASS: Patient deposit credit confirmation, Escape/focus, server rejection, success at ${width}px`);
            await page.close();
        }
    } finally { await browser.close(); }
})().catch(e=>{console.error(e);process.exit(1);});
