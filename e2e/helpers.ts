import { createHmac } from 'node:crypto';
import { execFileSync } from 'node:child_process';
import { Page, expect } from '@playwright/test';

// Clears every enrolled method for a user. Also exercises the recovery CLI.
export function resetTwoFactor(user = 'admin') {
	execFileSync('npx', ['@wordpress/env', 'run', 'cli', 'wp', 'sigil', 'reset', user], { stdio: 'ignore' });
}

const B32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

export function base32Decode(b32: string): Buffer {
	const clean = b32.toUpperCase().replace(/[^A-Z2-7]/g, '');
	let bits = '';
	for (const c of clean) {
		bits += B32.indexOf(c).toString(2).padStart(5, '0');
	}
	const bytes: number[] = [];
	for (let i = 0; i + 8 <= bits.length; i += 8) {
		bytes.push(parseInt(bits.slice(i, i + 8), 2));
	}
	return Buffer.from(bytes);
}

export function totp(secret: string, at: number = Date.now()): string {
	const counter = Math.floor(at / 1000 / 30);
	const buf = Buffer.alloc(8);
	buf.writeUInt32BE(Math.floor(counter / 2 ** 32), 0);
	buf.writeUInt32BE(counter >>> 0, 4);
	const hmac = createHmac('sha1', base32Decode(secret)).update(buf).digest();
	const offset = hmac[19] & 0x0f;
	const bin =
		((hmac[offset] & 0x7f) << 24) |
		((hmac[offset + 1] & 0xff) << 16) |
		((hmac[offset + 2] & 0xff) << 8) |
		(hmac[offset + 3] & 0xff);
	return (bin % 1_000_000).toString().padStart(6, '0');
}

export async function login(page: Page, user = 'admin', pass = 'password') {
	await page.goto('/wp-login.php');
	await page.fill('#user_login', user);
	await page.fill('#user_pass', pass);
	await page.click('#wp-submit');
}

export async function logout(page: Page) {
	// Clearing the session cookies is deterministic; the admin-bar logout link
	// depends on the bar rendering and hover state, which is flaky under test.
	await page.context().clearCookies();
}

// Logout that mimics real WordPress: clears only the WP auth cookies and leaves
// the trusted-device cookie in place, the way a browser would after logging out.
export async function logoutKeepingTrust(page: Page) {
	const ctx = page.context();
	const cookies = await ctx.cookies();
	await ctx.clearCookies();
	const keep = cookies.filter((c) => !c.name.startsWith('wordpress'));
	if (keep.length) await ctx.addCookies(keep);
}
