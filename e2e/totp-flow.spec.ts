import { test, expect } from '@playwright/test';
import { resetTwoFactor, login, logout, totp } from './helpers';

// Each run starts from a clean slate.
test.beforeEach(() => resetTwoFactor());

// Each guess consumes the challenge token, so a mistyped code must hand the form
// a fresh one. Getting this wrong turns a typo into "log in again".
test('a wrong code can be corrected without starting over', async ({ page }) => {
	await login(page);
	await page.goto('/wp-admin/users.php?page=sigil-2fa-setup&method=totp');
	const secret = await page.locator('input[name="sigil_totp_secret"]').inputValue();
	await page.fill('input[name="sigil_totp_code"]', totp(secret));
	await page.locator('button[type="submit"], input[type="submit"]').filter({ hasText: /enrol|enable|verify|confirm|save/i }).first().click();

	await logout(page);
	await login(page);

	const firstToken = await page.locator('#sigil-challenge-form input[name="sigil_token"]').inputValue();

	await page.fill('#sigil-challenge-form input[name="code"]', '000000');
	await page.locator('#sigil-authenticate').click();
	await expect(page.locator('body')).toContainText(/verification failed/i);

	// The form must now be carrying a different, live token.
	const retryToken = await page.locator('#sigil-challenge-form input[name="sigil_token"]').inputValue();
	expect(retryToken).not.toBe(firstToken);
	expect(retryToken).toMatch(/^[a-f0-9]{64}$/);

	await page.fill('#sigil-challenge-form input[name="code"]', totp(secret));
	await page.locator('#sigil-authenticate').click();
	await page.waitForLoadState('networkidle');

	await page.goto('/wp-admin/');
	await expect(page.locator('#wpadminbar')).toBeVisible();
});

// The real go/no-go: a human enrols TOTP, logs out, is challenged, and gets back in.
test('enrol TOTP, get challenged at login, and pass it', async ({ page }) => {
	await login(page);

	// Setup page lives under the Users menu; the TOTP tab is ?method=totp.
	await page.goto('/wp-admin/users.php?page=sigil-2fa-setup&method=totp');
	await expect(page.locator('body')).toContainText(/two-factor|2fa|authenticat/i);

	// The TOTP panel renders a hidden secret + a code field.
	const secretLoc = page.locator('input[name="sigil_totp_secret"]');
	await expect(secretLoc).toHaveCount(1);
	const secret = await secretLoc.inputValue();
	expect(secret.length).toBeGreaterThanOrEqual(16);

	await page.fill('input[name="sigil_totp_code"]', totp(secret));
	await page.locator('button[type="submit"], input[type="submit"]').filter({ hasText: /enrol|enable|verify|confirm|save/i }).first().click();

	// Backup codes must be shown exactly once, at first enrolment.
	await expect(page.locator('body')).toContainText(/backup code/i);

	await logout(page);

	// Log back in — the challenge must intercept before admin is reachable.
	await login(page);
	await expect(page.locator('body')).toContainText(/authenticator code|two-factor|verification/i);
	expect(page.url()).not.toContain('/wp-admin/index.php');

	// Pass the challenge with a fresh code. The challenge field is name="code";
	// use the real authenticate button, not a per-method switcher form.
	await page.fill('#sigil-challenge-form input[name="code"]', totp(secret));
	await page.locator('#sigil-authenticate').click();
	await page.waitForLoadState('networkidle');

	// Now admin is reachable.
	await page.goto('/wp-admin/');
	await expect(page.locator('#wpadminbar')).toBeVisible();
});
