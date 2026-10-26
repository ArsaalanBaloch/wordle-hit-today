import { readdir, readFile, stat } from "node:fs/promises";
import path from "node:path";
import process from "node:process";

const publicRoot = path.resolve(process.argv[2] ?? "var/site");
const errors = [];
const warnings = [];

async function walk(directory) {
  const entries = await readdir(directory, { withFileTypes: true });
  const files = [];

  for (const entry of entries) {
    const entryPath = path.join(directory, entry.name);
    if (entry.isDirectory()) {
      files.push(...(await walk(entryPath)));
    } else {
      files.push(entryPath);
    }
  }

  return files;
}

function decodeHtml(value) {
  const named = {
    amp: "&",
    apos: "'",
    gt: ">",
    lt: "<",
    quot: '"',
  };

  return value.replace(
    /&(#x[\da-f]+|#\d+|amp|apos|gt|lt|quot);/gi,
    (entity, token) => {
      if (token.startsWith("#x")) {
        return String.fromCodePoint(Number.parseInt(token.slice(2), 16));
      }
      if (token.startsWith("#")) {
        return String.fromCodePoint(Number.parseInt(token.slice(1), 10));
      }

      return named[token.toLowerCase()];
    },
  );
}

function attribute(tag, name) {
  const match = tag.match(new RegExp(`\\b${name}\\s*=\\s*(["'])(.*?)\\1`, "i"));
  return match ? decodeHtml(match[2]) : null;
}

function tags(html, name) {
  return [...html.matchAll(new RegExp(`<${name}\\b[^>]*>`, "gi"))].map(
    ([tag]) => tag,
  );
}

function metadata(html, key, value) {
  return tags(html, "meta")
    .filter((tag) => attribute(tag, key)?.toLowerCase() === value)
    .map((tag) => attribute(tag, "content"));
}

function linkValues(html, relation) {
  return tags(html, "link")
    .filter((tag) =>
      (attribute(tag, "rel") ?? "")
        .toLowerCase()
        .split(/\s+/)
        .includes(relation),
    )
    .map((tag) => attribute(tag, "href"));
}

function textValues(html, tagName) {
  return [
    ...html.matchAll(
      new RegExp(`<${tagName}\\b[^>]*>([\\s\\S]*?)</${tagName}>`, "gi"),
    ),
  ].map((match) =>
    decodeHtml(
      match[1]
        .replace(/<[^>]+>/g, " ")
        .replace(/\s+/g, " ")
        .trim(),
    ),
  );
}

function routeFor(file) {
  const relative = path.relative(publicRoot, file).split(path.sep).join("/");
  if (relative === "index.html") return "/";
  if (relative.endsWith("/index.html")) {
    return `/${relative.slice(0, -"index.html".length)}`;
  }

  return `/${relative}`;
}

function localFileFor(pathname) {
  const cleanPath = decodeURIComponent(pathname).replace(/^\/+/, "");
  if (cleanPath === "") return path.join(publicRoot, "index.html");
  if (pathname.endsWith("/")) {
    return path.join(publicRoot, cleanPath, "index.html");
  }

  return path.join(publicRoot, cleanPath);
}

function duplicateValues(pages, property) {
  const grouped = new Map();
  for (const page of pages) {
    const value = page[property];
    if (!value) continue;
    grouped.set(value, [...(grouped.get(value) ?? []), page.route]);
  }

  return [...grouped.entries()].filter(([, routes]) => routes.length > 1);
}

function report(collection, message, route = null) {
  collection.push(route ? `${route}: ${message}` : message);
}

await stat(publicRoot).catch(() => {
  throw new Error(`Generated public root does not exist: ${publicRoot}`);
});

const allFiles = await walk(publicRoot);
const htmlFiles = allFiles.filter((file) => file.endsWith(".html"));
const sitemapPath = path.join(publicRoot, "sitemap.xml");
const robotsPath = path.join(publicRoot, "robots.txt");
const sitemap = await readFile(sitemapPath, "utf8");
const robots = await readFile(robotsPath, "utf8");
const sitemapEntries = [...sitemap.matchAll(/<url>([\s\S]*?)<\/url>/g)].map(
  ([, block]) => ({
    location: decodeHtml(block.match(/<loc>(.*?)<\/loc>/)?.[1] ?? ""),
    lastModified: block.match(/<lastmod>(.*?)<\/lastmod>/)?.[1] ?? null,
  }),
);
const sitemapLocations = sitemapEntries.map(({ location }) => location);
const sitemapOrigin = sitemapLocations[0]
  ? new URL(sitemapLocations[0]).origin
  : null;
const fixtureMode = sitemapOrigin === "https://example.invalid";
const pages = [];

for (const file of htmlFiles) {
  const html = await readFile(file, "utf8");
  const route = routeFor(file);
  const titles = textValues(html, "title");
  const descriptions = metadata(html, "name", "description");
  const canonicals = linkValues(html, "canonical");
  const headings = textValues(html, "h1");
  const robotsDirectives = metadata(html, "name", "robots");
  const noindex = robotsDirectives.some((value) =>
    value
      ?.toLowerCase()
      .split(/\s*,\s*/)
      .includes("noindex"),
  );
  const nofollow = robotsDirectives.some((value) =>
    value
      ?.toLowerCase()
      .split(/\s*,\s*/)
      .includes("nofollow"),
  );
  const indexable = !noindex;
  const canonicalRequired = indexable || (fixtureMode && route !== "/404.html");
  const page = {
    route,
    title: titles[0] ?? null,
    description: descriptions[0] ?? null,
    canonical: canonicals[0] ?? null,
    h1: headings[0] ?? null,
    indexable,
  };
  pages.push(page);

  if (fixtureMode && (!noindex || !nofollow)) {
    report(errors, "fixture page must declare noindex, nofollow", route);
  }

  if (titles.length !== 1)
    report(errors, `expected one title, found ${titles.length}`, route);
  if (descriptions.length !== 1) {
    report(
      errors,
      `expected one meta description, found ${descriptions.length}`,
      route,
    );
  }
  if (headings.length !== 1)
    report(errors, `expected one h1, found ${headings.length}`, route);
  if (!/<html\b[^>]*\blang=["']en["']/i.test(html)) {
    report(errors, 'missing html lang="en"', route);
  }
  if (metadata(html, "name", "viewport").length !== 1) {
    report(errors, "expected one viewport meta tag", route);
  }

  if (canonicalRequired) {
    if (canonicals.length !== 1) {
      report(
        errors,
        `expected one canonical URL, found ${canonicals.length}`,
        route,
      );
    } else if (sitemapOrigin && canonicals[0] !== `${sitemapOrigin}${route}`) {
      report(
        errors,
        `canonical does not match its public route (${canonicals[0]})`,
        route,
      );
    }

    const socialChecks = [
      ["property", "og:title", page.title],
      ["property", "og:description", page.description],
      ["property", "og:url", page.canonical],
      ["name", "twitter:title", page.title],
      ["name", "twitter:description", page.description],
    ];
    for (const [key, value, expected] of socialChecks) {
      const matches = metadata(html, key, value);
      if (matches.length !== 1 || matches[0] !== expected) {
        report(
          errors,
          `${value} must occur once and match primary metadata`,
          route,
        );
      }
    }
  } else if (canonicals.length > 0) {
    report(warnings, "non-indexable page has a canonical URL", route);
  }

  if (page.title && (page.title.length < 20 || page.title.length > 70)) {
    report(warnings, `title length is ${page.title.length} characters`, route);
  }
  if (
    canonicalRequired &&
    page.description &&
    (page.description.length < 70 || page.description.length > 165)
  ) {
    report(
      warnings,
      `meta description length is ${page.description.length} characters`,
      route,
    );
  }

  for (const [, json] of html.matchAll(
    /<script\b[^>]*type=["']application\/ld\+json["'][^>]*>([\s\S]*?)<\/script>/gi,
  )) {
    try {
      const data = JSON.parse(json);
      if (data["@context"] !== "https://schema.org") {
        report(errors, "JSON-LD uses an unexpected @context", route);
      }
    } catch {
      report(errors, "contains invalid JSON-LD", route);
    }
  }

  const headingLevels = [
    ...html.matchAll(/<h([1-6])\b[^>]*>[\s\S]*?<\/h\1>/gi),
  ].map((match) => Number(match[1]));
  for (let index = 1; index < headingLevels.length; index += 1) {
    if (headingLevels[index] > headingLevels[index - 1] + 1) {
      report(
        errors,
        `heading hierarchy skips from h${headingLevels[index - 1]} to h${headingLevels[index]}`,
        route,
      );
    }
  }

  for (const image of tags(html, "img")) {
    if (attribute(image, "alt") === null) {
      report(errors, "image is missing an alt attribute", route);
    }
  }

  for (const anchor of tags(html, "a")) {
    const href = attribute(anchor, "href");
    if (!href || href.startsWith("#") || href.startsWith("mailto:")) continue;

    let url;
    try {
      url = new URL(
        href,
        page.canonical ?? `${sitemapOrigin ?? "https://audit.invalid"}${route}`,
      );
    } catch {
      report(errors, `invalid link URL: ${href}`, route);
      continue;
    }
    if (sitemapOrigin && url.origin !== sitemapOrigin) continue;
    const localFile = localFileFor(url.pathname);
    if (!allFiles.includes(localFile)) {
      report(
        errors,
        `internal link has no generated target: ${url.pathname}`,
        route,
      );
    }
  }
}

const canonicalPages = pages.filter(
  ({ route, indexable }) => indexable || (fixtureMode && route !== "/404.html"),
);
for (const [value, routes] of duplicateValues(canonicalPages, "title")) {
  report(errors, `duplicate title on ${routes.join(", ")}: ${value}`);
}
for (const [value, routes] of duplicateValues(canonicalPages, "description")) {
  report(
    errors,
    `duplicate meta description on ${routes.join(", ")}: ${value}`,
  );
}

const canonicalSet = new Set(canonicalPages.map(({ canonical }) => canonical));
const sitemapSet = new Set(sitemapLocations);
for (const canonical of canonicalSet) {
  if (canonical && !sitemapSet.has(canonical)) {
    report(errors, `indexable canonical missing from sitemap: ${canonical}`);
  }
}
for (const location of sitemapSet) {
  if (!canonicalSet.has(location)) {
    report(errors, `sitemap URL is not an indexable canonical: ${location}`);
  }
}
if (sitemapSet.size !== sitemapLocations.length) {
  report(errors, "sitemap contains duplicate URLs");
}
for (const { location, lastModified } of sitemapEntries) {
  if (lastModified && !/^\d{4}-\d{2}-\d{2}$/.test(lastModified)) {
    report(errors, `sitemap lastmod is not YYYY-MM-DD: ${location}`);
  }
}
if (!robots.endsWith("\n")) report(warnings, "robots.txt has no final newline");
if (
  robots.includes("Disallow: /") &&
  sitemapOrigin !== "https://example.invalid"
) {
  report(errors, "non-fixture canonical origin is blocked by robots.txt");
}
if (
  !robots.includes("Disallow: /") &&
  !robots.includes(`Sitemap: ${sitemapOrigin}/sitemap.xml`)
) {
  report(errors, "indexable robots.txt does not declare the canonical sitemap");
}

console.log(
  `SEO audit: ${htmlFiles.length} HTML pages, ${sitemapEntries.length} sitemap URLs`,
);
console.log(`Errors: ${errors.length}; warnings: ${warnings.length}`);
for (const error of errors) console.log(`ERROR ${error}`);
for (const warning of warnings) console.log(`WARN  ${warning}`);

if (errors.length > 0) process.exitCode = 1;
