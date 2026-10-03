import { defineConfig, devices } from "@playwright/test";

/** Engagement islands on the test-only sandbox page (see sandbox-server.php). */
export default defineConfig({
  testDir: ".",
  testMatch: "sandbox.spec.ts",
  fullyParallel: false,
  reporter: "list",
  outputDir: "../../storage/engagement-test-results",
  use: { baseURL: process.env.SANDBOX_URL ?? "http://127.0.0.1:3340", trace: "retain-on-failure" },
  projects: [
    { name: "chromium", use: { ...devices["Desktop Chrome"] } },
    { name: "mobile", use: { ...devices["Pixel 7"] } },
  ],
});
