import { expect, test } from "./fixtures.js";

test("homepage meets local render stability and main-thread guardrails", async ({
  page,
}) => {
  await page.addInitScript(() => {
    globalThis.__labVitals = { cls: 0, totalBlockingTime: 0 };
    new PerformanceObserver((list) => {
      for (const entry of list.getEntries()) {
        if (!entry.hadRecentInput) globalThis.__labVitals.cls += entry.value;
      }
    }).observe({ type: "layout-shift", buffered: true });
    new PerformanceObserver((list) => {
      for (const entry of list.getEntries()) {
        globalThis.__labVitals.totalBlockingTime += Math.max(
          0,
          entry.duration - 50,
        );
      }
    }).observe({ type: "longtask", buffered: true });
  });
  await page.addInitScript(() => {
    const RealDate = Date;
    const fixed = new RealDate("2026-10-16T12:00:00Z");
    globalThis.Date = class extends RealDate {
      constructor(...args) {
        super(...(args.length ? args : [fixed]));
      }
      static now() {
        return fixed.getTime();
      }
    };
  });

  await page.goto("/");
  await expect(page.locator("[data-today-content]")).toBeVisible();
  await page.waitForTimeout(500);

  const vitals = await page.evaluate(() => ({
    ...globalThis.__labVitals,
    firstContentfulPaint:
      performance.getEntriesByName("first-contentful-paint")[0]?.startTime ??
      null,
  }));
  expect(vitals.firstContentfulPaint).not.toBeNull();
  expect(vitals.firstContentfulPaint).toBeLessThan(1_800);
  expect(vitals.cls).toBeLessThan(0.1);
  // This routed headless harness includes Playwright instrumentation overhead;
  // keep a stable regression ceiling while production field INP remains a
  // Search Console/PageSpeed measurement.
  expect(vitals.totalBlockingTime).toBeLessThan(350);
});
