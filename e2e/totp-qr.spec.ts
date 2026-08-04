import { test, expect, Page } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { resetTwoFactor, login } from './helpers';

test.beforeEach(() => resetTwoFactor());

/**
 * Decode whatever QR is on the page, using a decoder that knows nothing about
 * the encoder that drew it. Rendering something QR-shaped is not the same as
 * rendering something an authenticator app can read.
 */
async function decodeQr(page: Page): Promise<string | null> {
	const jsqr = readFileSync(require.resolve('jsqr/dist/jsQR.js'), 'utf8');
	await page.addScriptTag({ content: jsqr });

	return page.evaluate(async () => {
		const svg = document.querySelector('.sigil-totp-qr svg');
		if (!svg) return null;

		// Rasterise the SVG so the decoder sees it the way a camera would.
		const source = new XMLSerializer().serializeToString(svg);
		const url = 'data:image/svg+xml;base64,' + btoa(source);
		const img = new Image();
		img.width = 480;
		img.height = 480;
		await new Promise((resolve, reject) => {
			img.onload = resolve;
			img.onerror = reject;
			img.src = url;
		});

		const canvas = document.createElement('canvas');
		canvas.width = 480;
		canvas.height = 480;
		const ctx = canvas.getContext('2d')!;
		ctx.fillStyle = '#fff';
		ctx.fillRect(0, 0, canvas.width, canvas.height);
		ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

		const data = ctx.getImageData(0, 0, canvas.width, canvas.height);
		// @ts-expect-error injected into the page above
		const result = window.jsQR(data.data, data.width, data.height);

		return result ? result.data : null;
	});
}

test('the enrolment QR decodes to the exact provisioning URI', async ({ page }) => {
	await login(page);
	await page.goto('/wp-admin/users.php?page=sigil-2fa-setup&method=totp');

	await expect(page.locator('.sigil-totp-qr svg')).toBeVisible();

	const uri = await page.locator('#sigil-totp-uri').innerText();
	const decoded = await decodeQr(page);

	expect(decoded).toBe(uri.trim());
	expect(decoded).toMatch(/^otpauth:\/\/totp\//);
	expect(decoded).toContain(await page.locator('#sigil-totp-secret').innerText());
});

test('the QR renders on the front-end enrolment page too', async ({ page }) => {
	await login(page);
	await page.goto('/two-factor/');
	await page.locator('.sigil-enrol__tab', { hasText: /authenticator/i }).first().click();

	await expect(page.locator('.sigil-totp-qr svg')).toBeVisible();

	const uri = await page.locator('#sigil-totp-uri').innerText();
	expect(await decodeQr(page)).toBe(uri.trim());
});
