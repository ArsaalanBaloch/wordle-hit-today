import { test as base } from "@playwright/test";
import { readFile, stat } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";

const projectRoot = path.dirname(
  path.dirname(path.dirname(fileURLToPath(import.meta.url))),
);
const publicRoot = path.join(projectRoot, "var", "site");

const contentTypes = {
  ".css": "text/css; charset=utf-8",
  ".html": "text/html; charset=utf-8",
  ".js": "text/javascript; charset=utf-8",
  ".json": "application/json; charset=utf-8",
  ".txt": "text/plain; charset=utf-8",
  ".woff2": "font/woff2",
  ".xml": "application/xml; charset=utf-8",
};

export const test = base.extend({
  page: async ({ page }, use) => {
    await installStaticRoutes(page);
    await use(page);
  },
});

export async function installStaticRoutes(page) {
  await page.route("http://wordle.test/**", async (route) => {
    const url = new URL(route.request().url());
    if (url.pathname === "/index.html") {
      await route.fulfill({
        status: 301,
        headers: { location: `http://wordle.test/${url.search}` },
      });
      return;
    }
    if (url.pathname === "/wordle-answers/page/1/") {
      await route.fulfill({
        status: 301,
        headers: { location: "http://wordle.test/wordle-answers/" },
      });
      return;
    }

    const pathname = decodeURIComponent(url.pathname);
    if (pathname.includes("..") || pathname.includes("\0")) {
      await fulfillNotFound(route);
      return;
    }

    let relative =
      pathname === "/" ? "index.html" : pathname.replace(/^\//, "");
    if (pathname !== "/" && pathname.endsWith("/")) relative += "index.html";
    const candidate = path.resolve(publicRoot, relative);
    if (
      !candidate
        .toLowerCase()
        .startsWith(`${publicRoot.toLowerCase()}${path.sep}`)
    ) {
      await fulfillNotFound(route);
      return;
    }

    try {
      const details = await stat(candidate);
      if (!details.isFile()) throw new Error("Not a file");
      const body = await readFile(candidate);
      const immutable = /\.[a-f0-9]{12}\.(?:css|js|json|woff2)$/.test(
        path.basename(candidate),
      );
      await route.fulfill({
        status: 200,
        body,
        headers: responseHeaders(candidate, immutable),
      });
    } catch {
      await fulfillNotFound(route);
    }
  });
}

export { expect } from "@playwright/test";

async function fulfillNotFound(route) {
  const body = await readFile(path.join(publicRoot, "404.html"));
  await route.fulfill({
    status: 404,
    body,
    headers: responseHeaders(path.join(publicRoot, "404.html"), false),
  });
}

function responseHeaders(filename, immutable) {
  const extension = path.extname(filename).toLowerCase();
  return {
    "content-type": contentTypes[extension] ?? "application/octet-stream",
    "cache-control": immutable
      ? "public, max-age=31536000, immutable"
      : filename.endsWith("published-dates.json") ||
          filename.endsWith("sitemap.xml")
        ? "max-age=60, must-revalidate"
        : "max-age=300, must-revalidate",
    "x-content-type-options": "nosniff",
    "referrer-policy": "strict-origin-when-cross-origin",
    "permissions-policy": "geolocation=(), camera=(), microphone=()",
    "content-security-policy":
      "default-src 'self'; script-src 'self'; style-src 'self'; font-src 'self'; img-src 'self' data:; connect-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'",
  };
}
