const fs=require('node:fs'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process');
const {chromium}=require('playwright');
(async()=>{
    const png=Buffer.from(execFileSync('C:/xampp/php/php.exe',['tests/paymentReceiptTest.php','--sample'],{encoding:'utf8',maxBuffer:3000000}),'base64');
    const html=execFileSync('C:/xampp/php/php.exe',['tests/paymentReceiptTest.php','--preview'],{encoding:'utf8'});
    const browser=await chromium.launch({channel:'msedge',headless:true});
    try {
        for (const width of [1440,390]) {
            const page=await browser.newPage({viewport:{width,height:900}}),errors=[];
            page.on('pageerror',e=>errors.push(e.message));
            await page.route('**/*',async route=>{
                const url=new URL(route.request().url());
                if (url.hostname!=='receipt.test') return route.abort();
                if (url.pathname.endsWith('payment-receipt-preview.css')) return route.fulfill({contentType:'text/css',body:fs.readFileSync('public/css/payment-receipt-preview.css')});
                if (url.pathname.endsWith('payment-receipt-preview.js')) return route.fulfill({contentType:'text/javascript',body:fs.readFileSync('public/js/payment-receipt-preview.js')});
                if (url.searchParams.has('download')) return route.fulfill({contentType:'image/png',headers:{'Content-Disposition':'attachment; filename="SAMPLE-0001.png"'},body:png});
                if (url.searchParams.has('billing_id')) return route.fulfill({contentType:'image/png',body:png});
                return route.fulfill({contentType:'text/html',body:html.replaceAll('href="/public/','href="http://receipt.test/public/').replaceAll('src="/public/','src="http://receipt.test/public/')});
            });
            await page.goto('http://receipt.test/');
            await page.waitForFunction(()=>document.getElementById('paymentReceiptImage').naturalWidth===1200);
            await page.screenshot({path:`test-results/payment-receipt-preview-${width}.png`,fullPage:true});
            assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'No page overflow at default zoom');
            await page.locator('#receiptZoomIn').click(); assert.equal(await page.locator('#receiptZoomLevel').innerText(),'125%');
            await page.locator('#receiptZoomReset').click(); assert.equal(await page.locator('#receiptZoomLevel').innerText(),'100%');
            await page.locator('#receiptZoomOut').click(); await page.locator('#receiptZoomOut').click(); assert(await page.locator('#receiptZoomOut').isDisabled());
            await page.locator('#receiptZoomReset').click();
            const [download]=await Promise.all([page.waitForEvent('download'),page.getByRole('link',{name:'Download PNG'}).click()]);
            assert.equal(download.suggestedFilename(),'SAMPLE-0001.png');
            assert.deepEqual(errors,[]); console.log(`PASS: PNG preview, responsive controls, zoom/reset/limits, download at ${width}px`);
            await page.close();
        }
    } finally { await browser.close(); }
})().catch(e=>{console.error(e);process.exit(1);});
