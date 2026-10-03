import fs from "node:fs";
import path from "node:path";
import { defineConfig, devices } from "@playwright/test";

const PORT = Number(process.env.E2E_PORT ?? 3100);
const baseURL = process.env.E2E_BASE_URL ?? `http://127.0.0.1:${PORT}`;
// Production mode refuses to start without an existing uploads folder (App\Support\ProductionConfig).
const uploadsDir = process.env.LOCAL_UPLOADS_DIR ?? path.resolve("storage/e2e-uploads");
fs.mkdirSync(uploadsDir, { recursive: true });

/**
 * The SAME behavioural suite as the Next.js reference (tests/e2e copied verbatim), run against
 * Laravel in production mode with built assets (`npm run build` first) and a seeded database:
 *   DB_DATABASE=sakila_laravel_e2e php artisan migrate:fresh --seed --force && npx playwright test
 */
export default defineConfig({
  testDir: "tests/e2e",
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [["github"], ["html", { open: "never" }]] : "list",
  use: { baseURL, trace: "retain-on-failure" },
  projects: [
    { name: "chromium", use: { ...devices["Desktop Chrome"] } },
    { name: "mobile", use: { ...devices["Pixel 7"] } },
  ],
  webServer: process.env.E2E_BASE_URL
    ? undefined
    : {
        command: `php artisan serve --host=127.0.0.1 --port=${PORT} --no-reload`,
        url: `${baseURL}/it`,
        reuseExistingServer: !process.env.CI,
        timeout: 120_000,
        env: {
          APP_ENV: "production",
          APP_DEBUG: "false",
          // php -S workers so parallel browsers are not serialised behind one request.
          PHP_CLI_SERVER_WORKERS: "6",
          // Tests send random X-Forwarded-For headers so per-IP limits never trip across runs:
          // the test runner on this machine is the "trusted proxy".
          TRUST_PROXY: "true",
          TRUSTED_PROXIES: "127.0.0.1",
          SESSION_SECURE_COOKIE: "false",
          LOCAL_UPLOADS_DIR: uploadsDir,
          ...(process.env.DB_DATABASE ? { DB_DATABASE: process.env.DB_DATABASE } : {}),
        },
      },
});
