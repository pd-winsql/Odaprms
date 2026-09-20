'use strict';

const assert = require('node:assert/strict');
const path = require('node:path');
const { chromium } = require('playwright');

const baseUrl = process.env.PATIENT_OFFLINE_BASE_URL;
const email = process.env.PATIENT_OFFLINE_EMAIL;
const password = process.env.PATIENT_OFFLINE_PASSWORD;
const uploadPath = process.env.PATIENT_OFFLINE_UPLOAD;

if (!baseUrl || !email || !password || !uploadPath) {
    throw new Error('Patient offline browser fixture environment is incomplete.');
}

const baseOrigin = new URL(baseUrl).origin;
const edge = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const chrome = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const brave = 'C:\\Program Files\\BraveSoftware\\Brave-Browser\\Application\\brave.exe';
const fs = require('node:fs');
const executablePath = [edge, chrome, brave].find(candidate => fs.existsSync(candidate));
if (!executablePath) throw new Error('No supported local Chromium browser was found.');

const failures = [];
const checks = [];
function check(condition, label) {
    if (!condition) throw new Error(label);
    checks.push(label);
    process.stdout.write(`PASS: ${label}\n`);
}

async function main() {
    const browser = await chromium.launch({ executablePath, headless: true });
    const context = await browser.newContext({ reducedMotion: 'reduce', viewport: { width: 1365, height: 900 } });
    const page = await context.newPage();
    const externalBlocked = [];
    const pageErrors = [];
    const criticalRequestFailures = [];

    await page.route('**/*', async route => {
        const requestUrl = route.request().url();
        const parsed = new URL(requestUrl);
        if (parsed.origin === baseOrigin || ['data:', 'blob:', 'about:'].includes(parsed.protocol)) {
            await route.continue();
            return;
        }
        externalBlocked.push(requestUrl);
        await route.abort('blockedbyclient');
    });

    page.on('pageerror', error => pageErrors.push(error.stack || error.message));
    page.on('console', message => {
        if (message.type() !== 'error') return;
        const locationUrl = message.location().url || '';
        if (locationUrl && !locationUrl.startsWith(baseOrigin)) return;
        const text = message.text();
        // Network failures are classified with request/response metadata below;
        // keeping them out of this bucket avoids treating a missing favicon as JS.
        if (!/Failed to load resource|favicon\.ico/i.test(text)) pageErrors.push(`console: ${text}`);
    });
    page.on('requestfailed', request => {
        const url = request.url();
        if (!url.startsWith(baseOrigin)) return;
        if (['document', 'script', 'stylesheet', 'xhr', 'fetch'].includes(request.resourceType())) {
            criticalRequestFailures.push(`${request.resourceType()}: ${url} (${request.failure()?.errorText || 'failed'})`);
        }
    });
    page.on('response', response => {
        if (!response.url().startsWith(baseOrigin) || response.status() < 400) return;
        const type = response.request().resourceType();
        if (['document', 'script', 'stylesheet', 'xhr', 'fetch'].includes(type)) {
            criticalRequestFailures.push(`${type}: ${response.url()} (HTTP ${response.status()})`);
        }
    });

    const nav = async (partial, landmark) => {
        await page.locator(`.vd-nav-item[data-page="${partial}"]`).click();
        await page.locator(landmark).waitFor({ state: 'visible' });
        check(await page.locator(landmark).isVisible(), `${partial} loads through the dynamic partial loader`);
    };
    const dismissWithEscape = async (modal, trigger, label) => {
        await page.locator(modal).waitFor({ state: 'visible' });
        await page.waitForTimeout(250);
        await page.keyboard.press('Escape');
        await page.locator(`${modal}.show`).waitFor({ state: 'hidden' });
        await page.waitForFunction(selector => document.activeElement?.matches(selector), trigger);
        check(true, `${label} closes with Escape and restores focus`);
    };

    try {
        await page.goto(`${baseUrl}/apps/views/login.php`, { waitUntil: 'domcontentloaded' });
        await page.locator('#loginIdentity').fill(email);
        await page.locator('#logPassword').fill(password);
        await Promise.all([
            page.waitForURL('**/apps/views/patient/dashboard.php*'),
            page.locator('#loginBtn').click(),
        ]);
        check(await page.locator('#dashTitle').textContent() === 'Home', 'Patient signs in through the real login form');

        const bootstrapVersion = await page.evaluate(() => window.bootstrap?.Modal?.VERSION || '');
        check(bootstrapVersion === '5.3.8', 'Locally served Bootstrap JavaScript is version 5.3.8');
        const bootstrapSource = await page.locator('script[src*="bootstrap.bundle.min.js"]').getAttribute('src');
        check(bootstrapSource?.startsWith('../../../public/js/bootstrap.bundle.min.js'), 'Patient dashboard loads the local Bootstrap bundle');

        const notificationTrigger = '#patientNotificationButton';
        await page.locator(notificationTrigger).click();
        await page.locator('#patientNotificationPanel').waitFor({ state: 'visible' });
        await page.keyboard.press('Escape');
        await page.waitForFunction(() => document.activeElement?.id === 'patientNotificationButton');
        check(await page.locator('#patientNotificationPanel').isHidden(), 'Notifications work offline and restore focus on Escape');

        const rescheduleTrigger = '[data-open-reschedule]';
        await page.locator(rescheduleTrigger).first().click();
        await dismissWithEscape('#patientRescheduleModal', rescheduleTrigger, 'Reschedule dialog');

        const withdrawalTrigger = '[data-withdraw-reschedule]';
        await page.locator(withdrawalTrigger).first().click();
        await dismissWithEscape('#staffActionModal', withdrawalTrigger, 'Cancellation/withdrawal confirmation');

        await nav('booking-content.php', '#bookingClinicTitle');
        await page.locator('#bookingNext').click();
        await page.locator('.vd-booking-schedule-card:not(.full)').first().click();
        await page.locator('#bookingNext').click();
        await page.locator('.vd-booking-service-option').first().click();
        const bookingSubmit = '#dashboardBookingSubmit';
        await page.locator(bookingSubmit).click();
        await dismissWithEscape('#bookingConfirmationModal', bookingSubmit, 'Appointment confirmation dialog');

        await nav('billing-content.php', '.depositSubmissionForm');
        const qrTrigger = '.vd-deposit-qr-trigger';
        await page.locator(qrTrigger).first().click();
        await dismissWithEscape('#gcashQrPreviewModal', qrTrigger, 'Payment QR dialog');
        await page.locator('.depositSubmissionForm input[type="file"]').first().setInputFiles(path.resolve(uploadPath));
        const receiptTrigger = '[data-receipt-view]';
        await page.locator(receiptTrigger).first().waitFor({ state: 'visible' });
        await page.waitForFunction(selector => !document.querySelector(selector)?.disabled, receiptTrigger);
        await page.locator(receiptTrigger).first().click();
        await dismissWithEscape('#uploadedReceiptPreviewModal', receiptTrigger, 'Uploaded receipt dialog');

        await nav('history-content.php', '[data-history-details]');
        const historyTrigger = '[data-history-details]';
        await page.locator(historyTrigger).first().click();
        await dismissWithEscape('#patientHistoryDetailsModal', historyTrigger, 'Visit details dialog');

        await nav('profile-content.php', '#profileWizardTitle');
        for (let expectedStep = 2; expectedStep <= 6; expectedStep++) {
            await page.locator('#profileNext').click();
            await page.waitForFunction(step => document.querySelector('#profileProgress')?.getAttribute('aria-valuenow') === String(step), expectedStep);
        }
        check(await page.locator('#profileStepPanel5').isVisible(), 'All six patient profile wizard steps remain functional');

        await nav('change-password-content.php', '#accountChangePasswordForm');
        check(await page.locator('#accountCurrentPw').isEditable(), 'Change-password workflow remains available');

        const chatTrigger = '.vd-chat-launch';
        await page.locator(chatTrigger).click();
        await page.locator('#clinicChatModal').waitFor({ state: 'visible' });
        const chatInput = '#clinicChatModal textarea';
        await page.locator(chatInput).fill('Offline browser regression message.');
        await page.locator('#clinicChatModal .vd-chat-send').click();
        await page.locator('#clinicChatModal .vd-chat-message', { hasText: 'Offline browser regression message.' }).waitFor();
        await dismissWithEscape('#clinicChatModal', chatTrigger, 'Clinic chat dialog');

        const logoutTrigger = '[data-logout-confirm]';
        await page.locator(logoutTrigger).click();
        await dismissWithEscape('#logoutModal', logoutTrigger, 'Logout confirmation');

        check(externalBlocked.length >= 2, 'All attempted external font/icon requests were blocked by the browser test');
        check(!pageErrors.some(error => /bootstrap is not defined/i.test(error)), 'No "bootstrap is not defined" runtime error occurred');
        check(pageErrors.length === 0, `No patient page errors occurred${pageErrors.length ? `: ${pageErrors.join(' | ')}` : ''}`);
        check(criticalRequestFailures.length === 0, `No critical local request failed${criticalRequestFailures.length ? `: ${criticalRequestFailures.join(' | ')}` : ''}`);
    } catch (error) {
        failures.push(error.stack || error.message);
    } finally {
        await browser.close();
    }

    if (failures.length) {
        process.stderr.write(`FAIL: ${failures.join('\n')}\n`);
        process.exitCode = 1;
    } else {
        process.stdout.write(`${checks.length} browser checks passed; external network remained blocked.\n`);
    }
}

main().catch(error => {
    process.stderr.write(`FAIL: ${error.stack || error.message}\n`);
    process.exitCode = 1;
});
