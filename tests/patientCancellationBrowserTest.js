const fs=require('node:fs'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process');
const {chromium}=require('playwright');
(async()=>{
    const fixture=execFileSync('C:/xampp/php/php.exe',['tests/patientCancellationFixture.php'],{encoding:'utf8'});
    const modal=fs.readFileSync('apps/views/shared/staff-action-modal.php','utf8');
    const browser=await chromium.launch({channel:'msedge',headless:true});
    try {
        for (const width of [1440,390]) {
            const page=await browser.newPage({viewport:{width,height:844}}); const errors=[]; let posts=0;
            page.on('pageerror',e=>errors.push(e.message));
            await page.route('**/*',r=>r.abort());
            await page.route('**/appointmentController.php',async route=>{
                posts++; const data=route.request().postData();
                for (const value of ['cancelPatient','qa-cancel-token','Cannot attend']) assert(data.includes(value));
                await route.fulfill({json: posts===1 ? {success:false,message:'Online cancellation is closed. Contact the clinic.'} : {success:true,message:'Appointment cancelled.'}});
            });
            await page.setContent(`<body class="vd-dash-body"><main class="vd-dash-main"><div class="vd-dash-content">${fixture}<a class="vd-nav-item" data-page="home-content.php">Home</a></div></main>${modal}</body>`);
            for (const name of ['bootstrap.min.css','styles.css','dashboard.css','patient-dashboard.css','ui-refinements.css']) await page.addStyleTag({content:fs.readFileSync('public/css/'+name,'utf8')});
            await page.evaluate(()=>{window.vdAppUrl=p=>'http://localhost/'+p;window.showToast=()=>{};document.querySelector('[data-page]').onclick=()=>window.qaRefresh=true;});
            for (const name of ['bootstrap.bundle.min.js','action-modal.js','patient-cancellation.js']) await page.addScriptTag({content:fs.readFileSync('public/js/'+name,'utf8')});
            const button=page.locator('[data-patient-cancel="101"]');
            assert(await page.locator('[data-patient-cancel="102"]').isDisabled());
            assert((await page.locator('#cancelNotice102').innerText()).includes('Contact the clinic'));
            assert((await page.locator('#cancelNotice101').innerText()).includes('Cancel before'));
            assert((await page.locator('[data-patient-cancel="103"]').innerText()).toLowerCase().includes('withdraw'));
            assert.equal(await page.locator('[data-patient-cancel="103"] [data-cancel-label]').innerText(),'Withdraw request');
            assert.equal(await page.locator('.vd-next-appt-card > .vd-patient-cancellation [data-patient-cancel="103"]').count(),1,'Withdrawal stays inside the next appointment card');
            assert.equal(await page.locator('.vd-next-appt-card .vd-patient-cancellation p').evaluate(el=>getComputedStyle(el).color),'rgb(255, 250, 240)','Helper text remains readable on gold');
            assert(await button.evaluate(el=>{const style=getComputedStyle(el);return style.borderTopStyle==='solid'&&parseFloat(style.borderTopWidth)>=1&&el.getBoundingClientRect().height>=44;}),'Action has a visible boundary and touch-sized target');
            await page.screenshot({path:require('node:path').join(require('node:os').tmpdir(),`patient-cancellation-${width}.png`)});
            await button.click(); await page.locator('[name="reason"]').waitFor();
            await page.waitForFunction(()=>document.activeElement===document.querySelector('[name="reason"]'));
            await page.keyboard.press('Escape');
            await page.waitForFunction(()=>document.activeElement===document.querySelector('[data-patient-cancel="101"]'));
            assert.equal(posts,0);
            await button.click(); await page.locator('[name="reason"]').fill('Cannot attend');
            await page.waitForFunction(()=>document.activeElement===document.querySelector('[name="reason"]'));
            await page.locator('#staffActionModalConfirm').click();
            await page.locator('[data-cancel-error]:not([hidden])').waitFor();
            assert.equal(posts,1); assert.equal(await button.isDisabled(),false);
            assert.equal(await button.locator('[data-cancel-label]').innerText(),'Cancel appointment');
            assert.equal(await button.locator('i[aria-hidden="true"]').count(),1,'Icon survives error/retry');
            await button.click(); await page.locator('[name="reason"]').fill('Cannot attend');
            await page.waitForFunction(()=>document.activeElement===document.querySelector('[name="reason"]'));
            await page.locator('#staffActionModalConfirm').click();
            await page.waitForFunction(()=>window.qaRefresh===true);
            assert.equal(posts,2); assert.deepEqual(errors,[]);
            assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
            console.log(`PASS: Cancellation deadline, blocked action, confirmation, Escape/focus, stale cutoff and success at ${width}px`);
            await page.close();

            const adminHtml=execFileSync('C:/xampp/php/php.exe',['tests/patientCancellationFixture.php','--settings'],{encoding:'utf8'});
            const admin=await browser.newPage({viewport:{width,height:844}}); const adminErrors=[]; let saved=false;
            admin.on('pageerror',e=>adminErrors.push(e.message));
            await admin.route('**/siteSettingsController.php',async route=>{
                const data=route.request().postData();
                assert(data.includes('cancellation')&&data.includes('minimum_cancellation_notice_days')&&data.includes('qa-cancel-token'));
                saved=true;
                await route.fulfill({json:{success:false,message:'QA submission captured without changing settings.'}});
            });
            await admin.setContent(`<body class="vd-dash-body"><main class="vd-dash-main"><div class="vd-dash-content"><section class="vd-dash-card">${adminHtml.slice(0,adminHtml.indexOf('<script>'))}</section></div></main></body>`);
            for (const name of ['bootstrap.min.css','styles.css','dashboard.css','ui-refinements.css']) await admin.addStyleTag({content:fs.readFileSync('public/css/'+name,'utf8')});
            await admin.addScriptTag({content:fs.readFileSync('public/js/bootstrap.bundle.min.js','utf8')});
            await admin.addScriptTag({content:fs.readFileSync('public/js/loading.js','utf8')});
            await admin.evaluate(()=>{window.vdAppUrl=p=>'http://localhost/'+p;window.showToast=()=>{};});
            await admin.addScriptTag({content:adminHtml.slice(adminHtml.indexOf('<script>')+8,adminHtml.lastIndexOf('</script>'))});
            const save=admin.locator('[data-group="cancellation"]');
            assert(await save.isDisabled(),'Unchanged cancellation setting cannot be saved');
            await admin.locator('#minimumCancellationNoticeDays').fill('3'); assert(!(await save.isDisabled()));
            await admin.screenshot({path:require('node:path').join(require('node:os').tmpdir(),`admin-cancellation-setting-${width}.png`)});
            await save.click(); await admin.locator('#settingsConfirmBtn').click();
            await admin.waitForFunction(()=>!document.querySelector('#settingsConfirmModal').classList.contains('show'));
            assert(saved); assert.deepEqual(adminErrors,[]);
            assert(await admin.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
            console.log(`PASS: Independent cancellation setting, dirty save and confirmation at ${width}px`);
            await admin.close();
        }
    }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
