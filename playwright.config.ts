import { defineConfig } from '@playwright/test';

export default defineConfig({
	testDir: './e2e',
	timeout: 60_000,
	fullyParallel: false,
	workers: 1,
	retries: 0,
	use: {
		baseURL: 'http://localhost:8877',
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},
});
