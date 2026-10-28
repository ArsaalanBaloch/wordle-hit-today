import { expect, test } from "./fixtures.js";

test("home metadata is evergreen, self-canonical and spoiler-free", async ({
  page,
}) => {
  const response = await page.goto("/");

  expect(response.status()).toBe(200);
  await expect(page).toHaveTitle(
    "Wordle Hit Today | Spoiler-Free Clues & Answer",
  );
  await expect(page.getByRole("heading", { level: 1 })).toHaveText(
    "Wordle Hint and Answer for October 16, 2026",
  );
  await expect(
    page.getByText(/Wordle #900016 · Progressive clues/),
  ).toBeVisible();
  await expect(page.locator('link[rel="canonical"]')).toHaveAttribute(
    "href",
    "https://example.invalid/",
  );
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute(
    "content",
    "noindex, nofollow",
  );
  await expect(page.locator('meta[property="og:title"]')).toHaveAttribute(
    "content",
    "Wordle Hit Today | Spoiler-Free Clues & Answer",
  );
  await expect(page.locator('meta[property="og:url"]')).toHaveAttribute(
    "content",
    "https://example.invalid/",
  );
  await expect(page.locator('meta[name="description"]')).not.toHaveAttribute(
    "content",
    /CRANE|October 16, 2026/,
  );
  await expect(
    page.locator('link[rel="icon"][type="image/png"]'),
  ).toHaveAttribute("href", "/favicon-48x48.png");
  await expect(page.locator('link[rel="apple-touch-icon"]')).toHaveAttribute(
    "href",
    "/apple-touch-icon.png",
  );
  const iconDimensions = await page.evaluate(async () => {
    return Promise.all(
      ["/favicon-48x48.png", "/apple-touch-icon.png"].map(
        (src) =>
          new Promise((resolve, reject) => {
            const image = new Image();
            image.addEventListener("load", () =>
              resolve([image.naturalWidth, image.naturalHeight]),
            );
            image.addEventListener("error", () => reject(new Error(src)));
            image.src = src;
          }),
      ),
    );
  });
  expect(iconDimensions).toEqual([
    [48, 48],
    [180, 180],
  ]);
});

test("historical metadata omits the solution", async ({ page }) => {
  await page.goto("/wordle-answers/2026-10-16/");

  await expect(page).toHaveTitle(
    "Wordle #900016 Answer & Hints — Oct 16, 2026 | Wordle Hit Today",
  );
  await expect(page.locator('meta[name="description"]')).not.toHaveAttribute(
    "content",
    /CRANE/,
  );
});

test("intent pages use distinct descriptive metadata and one main heading", async ({
  page,
}) => {
  const pages = [
    {
      path: "/wordle-solver/",
      title: "Wordle Solver and 5-Letter Word Finder | Wordle Hit Today",
      heading: /Wordle solver and five-letter word helper/i,
    },
    {
      path: "/wordle-answers/",
      title:
        "Wordle Archive: Past Answers by Date and Number | Wordle Hit Today",
      heading: /Wordle archive: past answers and hints/i,
    },
    {
      path: "/wordle-strategy/",
      title: "Wordle Strategy: Starting Words & Tips | Wordle Hit Today",
      heading: /Wordle strategy: starting words, tips and repeated letters/i,
    },
    {
      path: "/how-to-play-wordle/",
      title: "How to Play Wordle: Rules & Tile Colours | Wordle Hit Today",
      heading: /How to play Wordle: rules, colours and repeated letters/i,
    },
  ];

  for (const expected of pages) {
    await page.goto(expected.path);
    await expect(page).toHaveTitle(expected.title);
    await expect(page.locator("h1")).toHaveCount(1);
    await expect(page.getByRole("heading", { level: 1 })).toHaveText(
      expected.heading,
    );
    const description = await page
      .locator('meta[name="description"]')
      .getAttribute("content");
    expect(description?.length).toBeGreaterThanOrEqual(80);
  }
});

test("keyword clusters are answered on their assigned pages", async ({
  page,
}) => {
  await page.goto("/");
  await expect(
    page.getByRole("heading", { name: /Wordle hint today FAQ/i }),
  ).toBeVisible();
  await expect(page.getByText(/yesterday’s Wordle answer/i)).toBeVisible();

  await page.goto("/wordle-solver/");
  await expect(
    page.getByRole("heading", { name: /How to use this Wordle finder/i }),
  ).toBeVisible();

  await page.goto("/wordle-answers/");
  await expect(
    page.getByRole("heading", {
      name: /Used Wordle words and repeated answers/i,
    }),
  ).toBeVisible();
});

test("sitemap, robots and manifest match the contract", async ({ page }) => {
  await page.goto("/");
  const result = await page.evaluate(async () => {
    const [sitemapResponse, robotsResponse, manifestResponse] =
      await Promise.all([
        fetch("/sitemap.xml"),
        fetch("/robots.txt"),
        fetch("/assets/data/published-dates.json"),
      ]);
    const sitemap = await sitemapResponse.text();
    return {
      sitemapStatus: sitemapResponse.status,
      sitemap,
      sitemapUrls: [...sitemap.matchAll(/<loc>(.*?)<\/loc>/g)].map(
        (match) => match[1],
      ),
      robotsStatus: robotsResponse.status,
      robots: await robotsResponse.text(),
      manifestStatus: manifestResponse.status,
      manifestCache: manifestResponse.headers.get("cache-control"),
      manifest: await manifestResponse.json(),
    };
  });

  expect(result.sitemapStatus).toBe(200);
  expect(result.sitemap).toContain(
    "https://example.invalid/wordle-answers/2026-10-16/",
  );
  expect(new Set(result.sitemapUrls).size).toBe(result.sitemapUrls.length);
  expect(result.robotsStatus).toBe(200);
  expect(result.robots).toBe("User-agent: *\nDisallow: /\n");
  expect(result.manifestStatus).toBe(200);
  expect(result.manifestCache).toContain("max-age=60");
  expect(result.manifest.available_dates).toHaveLength(2);
  expect(JSON.stringify(result.manifest)).not.toContain("CRANE");
});

test("primary internal links resolve without errors", async ({ page }) => {
  const entryPages = [
    "/",
    "/wordle-solver/",
    "/wordle-answers/",
    "/wordle-strategy/",
    "/how-to-play-wordle/",
  ];

  for (const path of entryPages) {
    await page.goto(path);
    const results = await page.evaluate(async () => {
      const paths = [
        ...new Set(
          [...document.querySelectorAll('a[href^="/"]')].map(
            (link) => new URL(link.href).pathname,
          ),
        ),
      ];
      return Promise.all(
        paths.map(async (target) => ({
          target,
          status: (await fetch(target, { redirect: "manual" })).status,
        })),
      );
    });
    expect(results.filter(({ status }) => status >= 400)).toEqual([]);
  }
});

test("page-one alias responds with a permanent redirect", async ({ page }) => {
  const responses = [];
  page.on("response", (response) =>
    responses.push({ url: response.url(), status: response.status() }),
  );
  await page.goto("/wordle-answers/page/1/").catch(() => {
    // A fulfilled redirect has no real DNS target in the no-server browser harness.
  });

  expect(responses).toContainEqual({
    url: "http://wordle.test/wordle-answers/page/1/",
    status: 301,
  });
});

test("404 is noindex and protected by security headers", async ({ page }) => {
  const response = await page.goto("/not-published/");

  expect(response.status()).toBe(404);
  expect(response.headers()["x-content-type-options"]).toBe("nosniff");
  expect(response.headers()["content-security-policy"]).toContain(
    "frame-ancestors 'none'",
  );
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute(
    "content",
    "noindex, nofollow",
  );
  await expect(page.locator('link[rel="canonical"]')).toHaveCount(0);
  await expect(page.locator('meta[property="og:url"]')).toHaveCount(0);
});
