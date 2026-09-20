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

      const checkbox = page.getByRole('checkbox', { name: 'I agree to the Terms and Conditions' });
      const opener = page.getByRole('button', { name: 'Review required Terms and Conditions' });
      await expect(checkbox).toBeDisabled();
      await expect(checkbox).not.toBeChecked();
      await expect(opener).toHaveAttribute('aria-expanded', 'false');

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
      await expect(page.getByRole('link', { name: /Open full page/ })).toBeFocused();

      await page.keyboard.press('Escape');
      await expect(page.getByRole('dialog', { name: 'System Terms and Conditions' })).toBeHidden();
      await expect(opener).toBeFocused();

      await page.keyboard.press('Enter');
      await expect(page.locator('#systemTermsModalLabel')).toBeFocused();
      const scrollRegion = page.locator('#systemTermsScrollRegion');
      await scrollRegion.focus();
      await scrollRegion.evaluate((element) => {
        element.scrollTop = element.scrollHeight;
        element.dispatchEvent(new Event('scroll'));
      });
      await expect(page.getByRole('button', { name: 'Agree to Terms and close' })).toBeEnabled();
      await expect(scrollRegion).toBeFocused();
      await page.getByRole('button', { name: 'Agree to Terms and close' }).click();
      await expect(opener).toBeFocused();
      await expect(checkbox).toBeEnabled();
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

      const opener = page.getByRole('button', { name: 'Review required Terms and Conditions' });
      await opener.click();
      const dialogTransition = await page.locator('#systemTermsModal .modal-dialog').evaluate(
        (element) => getComputedStyle(element).transitionDuration
      );
      expect(dialogTransition).toBe('0s');
      await page.keyboard.press('Escape');
      await expect(page.getByRole('dialog', { name: 'System Terms and Conditions' })).toBeHidden();

      await page.locator('#regFirstName').fill('Accessible');
      await page.locator('#regLastName').fill('Tester');
      await page.locator('#regBirthdate').fill('2000-01-01');
      await page.locator('#regGender').selectOption('Prefer not to say');
      await page.locator('#regPhoneNumber').fill('09123456789');
      await page.locator('#regEmail').fill('accessible@example.invalid');
      await page.locator('#regPassword').fill('Secure123');
      await page.locator('#regConfirmPassword').fill('Secure123');
      await page.getByRole('button', { name: 'Create Account' }).click();
      await expect(page.getByRole('alert')).toHaveText('Please review and agree to the Terms and Conditions.');
      await expect(opener).toBeFocused();
      });
    });
  });
}
