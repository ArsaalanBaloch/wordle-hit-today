# SEO audit report

Audit date: 23 October 2026  
Scope: generated fixture site in `var/site` (13 HTML pages and 12 indexable sitemap URLs)

## Result

The first expanded audit found no hard failures and eight warnings. After remediation, the same audit reports **0 errors and 0 warnings**.

| Area                | Finding                                                                                     | Resolution                                                                                                 |
| ------------------- | ------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------- |
| 404 handling        | The non-indexable 404 page declared itself canonical and exposed canonical social metadata. | Removed canonical and URL-based social metadata from the 404 while retaining `noindex, follow`.            |
| Search titles       | Dated answer pages and two guides had titles likely to truncate in search results.          | Added concise SEO titles while retaining the fuller, useful on-page H1 headings.                           |
| Period metadata     | Year and month archive descriptions were too short to explain the pages.                    | Replaced them with distinct, descriptive copy covering date, number, answers and spoiler-controlled hints. |
| Pagination          | Archive page 2 and later would reuse page 1's description.                                  | Generated a page-specific description for every paginated archive route.                                   |
| Sitemap freshness   | Every sitemap URL received the build date, even if its content had not changed.             | Dated and archive routes now use record-derived dates; evergreen pages omit unverifiable `lastmod` values. |
| Regression coverage | The browser suite covered selected pages but not the complete generated tree.               | Added `npm run audit:seo` and included it in the browser validation chain.                                 |

## Automated checks

The generated-tree audit checks:

- one title, meta description and H1 per page;
- English language and viewport declarations;
- canonical URL correctness for every indexable route;
- Open Graph and Twitter metadata parity with primary metadata;
- duplicate titles and descriptions;
- title and description length warnings;
- valid JSON-LD and Schema.org context;
- heading hierarchy and image alt attributes;
- resolvable internal links;
- exact parity between indexable canonicals and sitemap URLs;
- unique sitemap URLs and valid `lastmod` date syntax; and
- robots/sitemap behavior appropriate to the configured origin.

The focused Playwright SEO suite also passes 8/8 scenarios, including spoiler-free metadata, intent-page targeting, internal links, redirect behavior and 404 handling.

## Intentional preview behavior

The fixture build remains private and non-indexable by design. It uses `https://example.invalid` for canonical URLs and `Disallow: /` in `robots.txt`. Production mode rejects that placeholder origin and emits an indexable robots file with the canonical sitemap location.

Keyword targeting remains organized by search intent in [SEO_KEYWORD_MAP.md](SEO_KEYWORD_MAP.md). No extra keyword repetitions were added during this audit because the existing page assignments already cover the relevant clusters without stuffing.

## Production-only checks still required

A real domain and hosting environment are not configured, so this local audit cannot yet validate DNS/TLS, live HTTP status and redirect chains, CDN/cache headers, field Core Web Vitals, Search Console indexing, rendered search snippets, crawl logs or production structured-data processing. Run the same audit against the production build, then complete hosted Lighthouse and search-engine verification after deployment.
