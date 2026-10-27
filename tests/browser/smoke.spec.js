import { expect, installStaticRoutes, test } from "./fixtures.js";

test("home resolves its labelled local date without exposing the answer first", async ({
  page,
}) => {
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

  await expect(
    page.getByText(
      "PRIVATE PREVIEW — today’s record may be source-validated; the archive and solver still use demo data",
    ),
  ).toBeVisible();
  await expect(page.locator("[data-today-content]")).toBeVisible();
  await expect(page.locator("[data-answer] .answer-tile").first()).toHaveText(
    "?",
  );
  await page.locator("[data-answer] .answer-tile").first().click();
  await expect(page.locator("[data-answer] .answer-tile").first()).toHaveText(
    "C",
  );
  await expect(page.locator("[data-share]")).not.toHaveAttribute(
    "data-share",
    /CRANE/,
  );
});

test("an explicit historical page remains authoritative", async ({ page }) => {
  await page.goto("/wordle-answers/2026-10-15/");

  await expect(page).toHaveURL(/2026-10-15\/$/);
  await expect(page.getByRole("heading", { level: 1 })).toContainText(
    "Wordle #900015",
  );
  await expect(page.locator("[data-answer] .answer-tile").first()).toHaveText(
    "?",
  );
});

test("homepage redirects to the published puzzle for the visitor's local date", async ({
  browser,
}) => {
  const context = await browser.newContext({
    timezoneId: "Pacific/Kiritimati",
  });
  const page = await context.newPage();
  await installStaticRoutes(page);
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
  await expect(page).toHaveURL(/\/wordle-answers\/2026-10-17\/$/);
  await context.close();
});

test("homepage shows one clear pending state when a local date is unavailable", async ({
  browser,
}) => {
  const context = await browser.newContext({
    timezoneId: "Pacific/Kiritimati",
  });
  const page = await context.newPage();
  await installStaticRoutes(page);
  await page.addInitScript(() => {
    const RealDate = Date;
    const fixed = new RealDate("2026-10-17T12:00:00Z");
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
  await expect(page.locator("[data-date-notice]")).toBeVisible();
  await expect(page.locator("[data-date-notice]")).toContainText(
    "has not been published",
  );
  await expect(page.locator("[data-today-content]")).toBeHidden();
  await context.close();
});

test("homepage keeps its labelled fallback usable when the manifest fails", async ({
  page,
}) => {
  await page.addInitScript(() => {
    const RealDate = Date;
    const fixed = new RealDate("2026-10-17T00:30:00Z");
    globalThis.Date = class extends RealDate {
      constructor(...args) {
        super(...(args.length ? args : [fixed]));
      }
      static now() {
        return fixed.getTime();
      }
    };
  });
  await page.route("**/assets/data/published-dates.json", (route) =>
    route.fulfill({ status: 503, body: "unavailable" }),
  );

  await page.goto("/");
  await expect(page.locator("[data-date-notice]")).toBeVisible();
  await expect(page.locator("[data-date-notice]")).toContainText(
    "check failed",
  );
  await expect(page.locator("[data-today-content]")).toBeVisible();
});

test("dated page labels a date that is later than the visitor's local day", async ({
  browser,
}) => {
  const context = await browser.newContext({
    timezoneId: "America/Los_Angeles",
  });
  const page = await context.newPage();
  await installStaticRoutes(page);
  await page.addInitScript(() => {
    const RealDate = Date;
    const fixed = new RealDate("2026-10-17T01:00:00Z");
    globalThis.Date = class extends RealDate {
      constructor(...args) {
        super(...(args.length ? args : [fixed]));
      }
      static now() {
        return fixed.getTime();
      }
    };
  });

  await page.goto("/wordle-answers/2026-10-17/");
  await expect(page.locator("[data-date-relation]")).toHaveText(
    "Published for an earlier timezone",
  );
  await expect(page.locator("[data-future-date-warning]")).toBeVisible();
  await context.close();
});

test("header navigation exposes every main page", async ({ page }) => {
  await page.goto("/");

  const navigation = page.getByRole("navigation", {
    name: "Primary navigation",
  });
  const expectedLinks = [
    ["Today", "/"],
    ["Solver", "/wordle-solver/"],
    ["Past Answers", "/wordle-answers/"],
    ["How to Play", "/how-to-play-wordle/"],
    ["Strategy", "/wordle-strategy/"],
    ["About", "/about/"],
    ["Privacy", "/privacy/"],
  ];

  for (const [label, path] of expectedLinks) {
    await expect(navigation.getByRole("link", { name: label })).toHaveAttribute(
      "href",
      path,
    );
  }
});

test("solver accepts direct feedback and handles an all-green row", async ({
  page,
}) => {
  await page.goto("/wordle-solver/");
  await expect(page.locator("[data-candidate-count]")).not.toHaveText(
    "0 candidates",
  );

  for (const [index, letter] of [..."CRANE"].entries()) {
    await page.locator(`#guess-1-${index + 1}`).fill(letter);
    await page.locator(`#feedback-1-${index + 1}`).selectOption("correct");
  }

  await expect(page.locator("[data-candidate-count]")).toHaveText(
    "1 candidate",
  );
  await expect(page.locator("[data-results]")).toContainText("CRANE");
});

test("archive exact-word search returns its dated link", async ({ page }) => {
  await page.goto("/wordle-answers/");
  await page.getByRole("searchbox").fill("crane");
  await page.getByRole("button", { name: "Search" }).click();

  await expect(page.locator("[data-search-status]")).toHaveText(
    "1 exact match.",
  );
  await expect(page.locator("[data-search-results]")).toContainText(
    "2026-10-16",
  );
});

test("missing routes return a real 404", async ({ page }) => {
  const response = await page.goto("/missing-page/");

  expect(response.status()).toBe(404);
  await expect(page.getByRole("heading", { level: 1 })).toHaveText(
    "That puzzle page is not available",
  );
});

test("no-JavaScript homepage links to an explicitly dated accessible fallback", async ({
  browser,
}) => {
  const context = await browser.newContext({ javaScriptEnabled: false });
  const page = await context.newPage();
  await installStaticRoutes(page);
  await page.goto("/");

  await expect(
    page.getByRole("heading", { name: "JavaScript is off" }),
  ).toBeVisible();
  await expect(
    page.getByRole("link", {
      name: /Wordle hints and answer for October 16, 2026/,
    }),
  ).toBeVisible();
  await expect(page.locator("[data-answer]")).toHaveCount(1);
  await expect(page.locator("[data-today-content]")).toBeHidden();
  await context.close();
});
