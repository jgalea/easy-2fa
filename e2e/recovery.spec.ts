import { test, expect } from '@playwright/test';
import { resetTwoFactor, login, logout, totp } from './helpers';

test.beforeEach(() => resetTwoFactor());

// A locked-out user must be able to get back in. This is the make-or-break flow.
test('backup code gets you in when the authenticator is gone, and CLI reset clears 2FA', async ({ page }) => {
	await login(page);
	await page.goto('/wp-admin/users.php?page=sigil-2fa-setup&method=totp');
	const secret = await page.locator('input[name="sigil_totp_secret"]').inputValue();
	await page.fill('input[name="sigil_totp_code"]', totp(secret));
	await page.locator('button[type="submit"], input[type="submit"]').filter({ hasText: /enrol|enable|verify|confirm|save/i }).first().click();

	// Capture a backup code from the one-time display. Codes are 8 chars from
	// the alphabet 23456789A-Z (no 0/O/1/I), rendered as <li><code>.
	await expect(page.locator('.sigil-backup-codes-list code').first()).toBeVisible();
	const codes = await page.locator('.sigil-backup-codes-list code').allInnerTexts();
	const backup = codes.map((c) => c.trim()).find((c) => /^[A-HJ-NP-Z2-9]{8}$/.test(c));
	expect(backup, 'a backup code should be shown').toBeTruthy();

	await logout(page);

	// Log in, switch to the backup-code method, and use one.
	await login(page);
	await expect(page.locator('body')).toContainText(/verification|authenticator/i);
	const switcher = page.locator('form.sigil-challenge__methods button', { hasText: /backup/i });
	if (await switcher.count()) await switcher.first().click();
	await page.fill('#sigil-challenge-form input[name="code"]', backup!.replace(/\s/g, ''));
	await page.locator('#sigil-authenticate').click();
	await page.waitForLoadState('networkidle');
	await page.goto('/wp-admin/');
	await expect(page.locator('#wpadminbar')).toBeVisible();

	// CLI reset is the last-resort escape hatch: after it, no second factor at all.
	resetTwoFactor();
	await logout(page);
	await login(page);
	await page.goto('/wp-admin/');
	await expect(page.locator('#wpadminbar')).toBeVisible();
});
