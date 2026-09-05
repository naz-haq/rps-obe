import { defineConfig, devices } from "@playwright/test";

export default defineConfig({
  testDir: "./tests/e2e",
  fullyParallel: true,
  // Browser + SSR builds compete for memory on local/CI machines.
  workers: 1,
  forbidOnly: Boolean(process.env.CI),
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? "github" : "list",
  use: {
    baseURL: "http://127.0.0.1:3011",
    trace: "retain-on-failure",
  },
  // Build first: start:test serves the existing standalone build, not source.
  // Never reuse a dev/production server that could point at real data or AI.
  webServer: [
    {
      command: "node tests/fixtures/generator-api.mjs",
      url: "http://127.0.0.1:8111/api/v1/health",
      reuseExistingServer: false,
      timeout: 30_000,
    },
    {
      command: "npm run start:test",
      url: "http://127.0.0.1:3011/login",
      env: { API_BASE_URL: "http://127.0.0.1:8111/api/v1" },
      reuseExistingServer: false,
      timeout: 30_000,
    },
  ],
  projects: [
    {
      name: "chromium",
      use: { ...devices["Desktop Chrome"] },
    },
  ],
});