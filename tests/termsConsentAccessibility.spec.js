const { test, expect, chromium, firefox } = require('@playwright/test');

const registrationUrl = 'http://127.0.0.1/Capstone%20System/apps/views/register.php';

async function withBrowserPage(browserConfig, viewport, callback) {
  const browser = await browserConfig.type.launch(browserConfig.channel ? { channel: browserConfig.channel } : {});
  const context = await browser.newContext({ viewport });
  const page = await context.newPage();
  try {
    await callback(page);
  } finally {
    await browser.close();
  }
}

test.setTimeout(60_000);

const browserConfigurations = [
  { name: 'Chromium', type: chromium, channel: 'chrome' },
  { name: 'Edge', type: chromium, channel: 'msedge' },
];
if (process.env.PLAYWRIGHT_FIREFOX === '1') {
  browserConfigurations.splice(1, 0, { name: 'Firefox', type: firefox });
}

for (const browser of browserConfigurations) {
  test.describe(`${browser.name} registration terms accessibility`, () => {
    test('has an accessible checkbox and keyboard-contained dialog focus flow', async () => {
      await withBrowserPage(browser, { width: 1280, height: 900 }, async (page) => {
      await page.goto(registrationUrl, { waitUntil: 'domcontentloaded' });
      expect(await page.evaluate(() => document.documentElement.scrollHeight > document.documentElement.clientHeight)).toBe(false);

      await page.locator('#regFirstName').fill('Accessible');
      await page.locator('#regLastName').fill('Tester');
      await page.locator('#regBirthdate').fill('2000-01-01');
      await page.locator('#regGender').selectOption('Prefer not to say');
      await page.getByRole('button', { name: 'Continue to account details' }).click();
      await expect(page.locator('#registerStepCount')).toHaveText('Step 2 of 2');
      expect(await page.evaluate(() => document.documentElement.scrollHeight > document.documentElement.clientHeight)).toBe(false);
      await page.getByRole('button', { name: 'Back', exact: true }).click();
      await expect(page.locator('#regFirstName')).toHaveValue('Accessible');
      await page.getByRole('button', { name: 'Continue to account details' }).click();

      const checkbox = page.getByRole('checkbox', { name: 'I agree to the Terms and Conditions' });
      const opener = page.getByRole('button', { name: 'Terms and Conditions', exact: true });
      await expect(checkbox).toBeEnabled();
      await expect(checkbox).not.toBeChecked();
      await expect(opener).toHaveAttribute('aria-expanded', 'false');

      await page.keyboard.press('Tab');
      await opener.focus();
      const focusOutline = await opener.evaluate((element) => getComputedStyle(element).outlineWidth);
      expect(focusOutline).not.toBe('0px');
      await page.keyboard.press('Enter');
      await expect(page.getByRole('dialog', { name: 'System Terms and Conditions' })).toBeVisible();
      await expect(opener).toHaveAttribute('aria-expanded', 'true');
      await expect(page.locator('#systemTermsModalLabel')).toBeFocused();

      await page.keyboard.press('Tab');
      await expect(page.getByRole('button', { name: 'Close Terms and Conditions dialog' })).toBeFocused();
      await page.keyboard.press('Shift+Tab');
      await expect(page.getByRole('button', { name: 'Close terms', exact: true })).toBeFocused();
      await page.keyboard.press('Shift+Tab');
      await expect(page.getByRole('link', { name: /Open full page/ })).toBeFocused();

      await page.keyboard.press('Escape');
      await expect(page.getByRole('dialog', { name: 'System Terms and Conditions' })).toBeHidden();
      await expect(opener).toBeFocused();

      await page.keyboard.press('Enter');
      await expect(page.locator('#systemTermsModalLabel')).toBeFocused();
      await page.getByRole('button', { name: 'Close terms', exact: true }).click();
      await expect(opener).toBeFocused();
      await expect(checkbox).not.toBeChecked();
      await checkbox.check();
      await expect(checkbox).toBeChecked();
      });
    });

    test('announces errors and supports reduced motion and 400% reflow', async () => {
      await withBrowserPage(browser, { width: 1280, height: 900 }, async (page) => {
      await page.emulateMedia({ reducedMotion: 'reduce' });
      await page.setViewportSize({ width: 640, height: 800 });
      await page.goto(registrationUrl, { waitUntil: 'domcontentloaded' });
      let horizontalOverflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
      expect(horizontalOverflow).toBe(false);

      await page.setViewportSize({ width: 320, height: 800 });
      horizontalOverflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
      expect(horizontalOverflow).toBe(false);

      await page.locator('#regFirstName').fill('Accessible');
      await page.locator('#regLastName').fill('Tester');
      await page.locator('#regBirthdate').fill('2000-01-01');
      await page.locator('#regGender').selectOption('Prefer not to say');
      await page.getByRole('button', { name: 'Continue to account details' }).click();

      const opener = page.getByRole('button', { name: 'Terms and Conditions', exact: true });
      await opener.click();
      const dialogTransition = await page.locator('#systemTermsModal .modal-dialog').evaluate(
        (element) => getComputedStyle(element).transitionDuration
      );
      expect(dialogTransition).toBe('0s');
      await page.keyboard.press('Escape');
      await expect(page.getByRole('dialog', { name: 'System Terms and Conditions' })).toBeHidden();

      await page.locator('#regPhoneNumber').fill('09123456789');
      await page.locator('#regEmail').fill('accessible@example.invalid');
      await page.locator('#regPassword').fill('Secure123');
      await page.locator('#regConfirmPassword').fill('Secure123');
      await page.getByRole('button', { name: 'Create Account' }).click();
      await expect(page.locator('#termsConsentError')).toHaveText('Please agree to the Terms and Conditions to create your account.');
      await expect(page.locator('#termsConsentError')).toBeVisible();
      await expect(page.getByRole('checkbox', { name: 'I agree to the Terms and Conditions' })).toBeFocused();
      await page.getByRole('checkbox', { name: 'I agree to the Terms and Conditions' }).check();
      await expect(page.locator('#termsConsentError')).toBeHidden();
      });
    });
  });
}
