import { defineConfig, devices } from "@playwright/test";
export default defineConfig({
  testDir: "./tests/browser",
  timeout: 30_000,
  expect: { timeout: 7_500 },
  fullyParallel: false,
  forbidOnly: true,
  retries: 0,
  workers: 1,
  reporter: [
    ["list"],
    ["html", { outputFolder: "playwright-report", open: "never" }],
  ],
  use: {
    baseURL: "http://wordle.test",
    trace: "retain-on-failure",
    screenshot: "only-on-failure",
  },
  projects: [
    {
      name: "browser-chromium",
      testMatch: /smoke\.spec\.js/,
      use: { ...devices["Desktop Chrome"] },
    },
    {
      name: "responsive",
      testMatch: /responsive\.spec\.js/,
      use: { ...devices["Desktop Chrome"] },
    },
    {
      name: "a11y",
      testMatch: /a11y\.spec\.js/,
      use: { ...devices["Desktop Chrome"] },
    },
    {
      name: "seo",
      testMatch: /seo\.spec\.js/,
      use: { ...devices["Desktop Chrome"] },
    },
  ],
});
