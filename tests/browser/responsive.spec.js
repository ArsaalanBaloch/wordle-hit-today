import path from "node:path";

import { expect, test } from "./fixtures.js";

const viewports = [
  { width: 320, height: 720, name: "mobile-320" },
  { width: 375, height: 812, name: "mobile-375" },
  { width: 768, height: 900, name: "tablet-768" },
  { width: 1024, height: 900, name: "desktop-1024" },
  { width: 1440, height: 1000, name: "desktop-1440" },
];

for (const viewport of viewports) {
  test(`home reflows without horizontal overflow at ${viewport.width}px`, async ({
    page,
  }) => {
    await page.setViewportSize(viewport);
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
    const overflow = await page.evaluate(
      () =>
        document.documentElement.scrollWidth >
        document.documentElement.clientWidth,
    );
    expect(overflow).toBe(false);
    await page.screenshot({
      path: path.join("docs", "screenshots", `home-${viewport.name}.png`),
      fullPage: true,
    });
  });
}

test("solver controls fit at 320px", async ({ page }) => {
  await page.setViewportSize({ width: 320, height: 720 });
  await page.goto("/wordle-solver/");

  const overflow = await page.evaluate(
    () =>
      document.documentElement.scrollWidth >
      document.documentElement.clientWidth,
  );
  expect(overflow).toBe(false);
  await page.screenshot({
    path: "docs/screenshots/solver-mobile-320.png",
    fullPage: true,
  });
});

test("mobile menu reveals every main navigation link", async ({ page }) => {
  await page.setViewportSize({ width: 320, height: 720 });
  await page.goto("/");

  await page.getByText("Menu", { exact: true }).click();
  await expect(
    page
      .getByRole("navigation", { name: "Primary navigation" })
      .getByRole("link", { name: "Privacy" }),
  ).toBeVisible();
});
