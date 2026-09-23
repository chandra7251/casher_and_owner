import { defineConfig } from "@playwright/test";

export default defineConfig({
  testDir: "./tests/browser",
  timeout: 30000,
  workers: 1,
  fullyParallel: false,
  use: { baseURL: "http://127.0.0.1:8765", headless: true },
  webServer: {
    command: "php artisan serve --host=127.0.0.1 --port=8765",
    url: "http://127.0.0.1:8765/api/health",
    reuseExistingServer: false,
    timeout: 30000,
  },
});
