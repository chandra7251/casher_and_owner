import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/browser',
  timeout: 30000,
  workers: 1,
  fullyParallel: false,
  use: { baseURL: 'http://127.0.0.1:8000', headless: true },
});
