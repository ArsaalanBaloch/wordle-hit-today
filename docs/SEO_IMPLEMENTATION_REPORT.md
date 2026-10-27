# SEO audit and keyword implementation report

Audit date: 27 October 2026  
Live site audited: `https://wordlehintoday.com/`  
Deployment: commit `6d1498c` pushed to GitHub and deployed to Hostinger

## Outcome

The implementation was committed, passed GitHub CI, and was deployed to Hostinger on 27 October 2026. Before deployment, the live homepage still labelled the 26 October puzzle even though the sitemap exposed the 27 October dated page. After deployment, the homepage correctly labels 27 October, the obsolete public source-check timestamp is absent, and the generated output passed live route, indexability, integrity, and browser checks.

The stale homepage and premature archive/sitemap exposure had one shared timing cause: a next-day record is legitimately fetched early for UTC+14 visitors, but publication previously treated every saved record as globally indexable. A second issue prevented a rebuild at UTC midnight when that record had already been saved. The local fix separates globally eligible date-page access from UTC indexability and triggers the required rollover build.

## Keyword dataset

Source: the separate `wordle_keywords_kd_volume.csv` supplied outside the repository.

- Original rows: 1,000
- Exact duplicate rows: 0
- Exact unique keywords: 1,000
- Normalized duplicate variants: 35
- Normalized unique forms: 965
- Relevant rows: 409
- Excluded rows: 591
- Exact phrases present before: 54
- Exact phrases present after: 57
- Relevant natural variations covered after: 352

US volume and KD are preserved verbatim in the row-level CSV but are not used for selection, exclusion, priority or placement. Decisions use relevance, intent, usefulness, page fit, coverage and truthful feature support.

Every source row is retained in `SEO_KEYWORD_COVERAGE.csv` with its original metrics, group, intent, target URL, priority, previous/final coverage, placement and exclusion reason. The generated summary is in `SEO_KEYWORD_SUMMARY.md`.

### Intent map

| Intent | Primary URL | Implementation |
| --- | --- | --- |
| Current daily hints and answer | `/` | Progressive hints, controlled answer reveal, local-date explanation and daily FAQ |
| Candidate filtering | `/wordle-solver/` | Feedback grid, five-letter finder guidance, repeats, vocabulary limitations and FAQ |
| Historical lookup | `/wordle-answers/` | Exact archive search, dated records, year/month navigation and archive FAQ |
| Rules | `/how-to-play-wordle/` | Six-guess loop, tile colours, repeats and Hard Mode context |
| Strategy | `/wordle-strategy/` | Starting-word evaluation, second guesses, repeats and Hard Mode |

The remaining 352 relevant variations are intentionally covered semantically instead of being repeated as exact phrases. This is an anti-stuffing choice, not an unassigned keyword gap. Future prioritization should use Search Console query evidence rather than KD or volume.

## Technical SEO findings

| Issue | Live/baseline result | Action taken locally | Final status |
| --- | --- | --- | --- |
| Future-dated archive entries | Confirmed. The 27 October page was exposed while the homepage remained on 26 October. | Kept the date URL and UTC+14 manifest entry available, but excluded pre-UTC records from archives, archive search indexes, recent cards and sitemap; added temporary `noindex, follow`; removed future adjacent links; added visitor-local date labels and warnings. | Fixed locally |
| Homepage UTC rollover | Confirmed. An already-saved next-day record did not cause a midnight rebuild. | Publication state now compares the expected UTC homepage date; scheduler passes its exact tick instant; rollover regression test added. | Fixed locally |
| Duplicate homepage hints | Confirmed in crawler text: interactive and no-JS copies repeated the same answer block. | Replaced the duplicate no-JS answer block with an accessible, independently dated page link. One answer block remains in HTML. | Fixed locally |
| Homepage conditional states | Conditions were legitimate, but multiple hidden error messages polluted extracted text. | Replaced them with one dynamically populated notice; verified success, redirect, pending, manifest-failure and missing-timezone behavior. | Improved and tested |
| `robots.txt` | Live response was `200 text/plain`, allowed public crawling and referenced the canonical sitemap. | Preserved behavior and validated generated production output. | Existing implementation correct |
| XML sitemap | Live response was `200 application/xml`; absolute apex canonicals and lastmod syntax were valid, but the next UTC date was exposed too early. | Filtered to UTC-indexable records, retained accurate record-derived lastmod, excluded noindex/future URLs and validated canonical parity. | Fixed locally |
| Canonicals/indexability | Homepage, solver, archive, guides and dated pages were self-canonical. HTTP and slash normalization worked; invalid routes returned 404. `www` has no DNS by owner choice. | Preserved self-canonicals; added explicit temporary future-page noindex handling; kept dated pages distinct from homepage; expanded regression auditing. | Correct locally; apex-only DNS remains intentional |
| Intent/cannibalization | Page purposes were mostly distinct but supporting phrasing and links could be clearer. | Kept daily terms on home, date/number terms on dated pages, solver terms on solver, historical terms on archive, and guide terms on their guides; strengthened natural copy and cross-links. | Improved |
| Archive/internal links | Archive was accurate but small, and future records appeared in its static lists. | Removed pre-UTC entries, preserved chronological order/pagination, and added useful links among home, solver, archive, rules and strategy without inventing records. | Fixed/improved |
| Solver loading state | Vocabulary and repeated-letter logic worked, but crawler HTML said `0 candidates` before initialization. | Replaced false zero with a loading label, added explicit unavailable state on fetch failure, and retained retry/filter/reset behavior. | Fixed; functional tests pass |
| Metadata | Unique titles/descriptions/H1s, spoiler-free metadata, OG/Twitter parity and dated values were already largely correct. | Revalidated every generated page, preserved unique intent, added useful headings/content, and retained no answer in metadata. | Correct and audited |
| Structured data | Homepage WebSite and dated BreadcrumbList were valid; other page types lacked breadcrumbs. | Added truthful BreadcrumbList data to solver, archive, period and editorial guide pages. No unsupported Organization, Article, rating or SearchAction claims were added. | Improved |
| Performance/CWV | Live host compression/cache behavior was good; field CWV was not available. | Confirmed Brotli and immutable hashed assets, kept CSS/JS below existing 30 KiB budgets, added local FCP/CLS/main-thread regression coverage, and verified responsive layouts. | Local guards pass; field LCP/INP pending |

## Live HTTP verification

- `https://wordlehintoday.com/`: 200, HTTPS, security headers, short HTML cache.
- `http://wordlehintoday.com/`: 301 to apex HTTPS.
- `/wordle-solver`: 301 to `/wordle-solver/`.
- Invalid route: real 404 with noindex page.
- `/robots.txt`: 200.
- `/sitemap.xml`: 200.
- Hashed CSS/JS: one-year immutable cache and Brotli compression.
- `www.wordlehintoday.com`: intentionally has no DNS record; the chosen canonical is apex-only.

Google PageSpeed Insights could not run because its API returned HTTP 429 quota exhausted. Real-user LCP, INP and CLS therefore remain external verification items for Search Console/CrUX after deployment.

## Validation results

| Check | Result |
| --- | --- |
| PHP CS Fixer + PHPStan + PHPUnit + Composer audit | Pass; 70 tests, 160 assertions, 2 symlink-only environment skips, no advisories |
| ESLint + Prettier + secret scan | Pass |
| Vitest | Pass; 27 tests |
| Browser behavior + responsive layouts | Pass; 18 tests |
| Automated accessibility | Pass; 4 routes, no serious/critical findings |
| Focused SEO browser suite | Pass; 8 tests |
| Local performance guard | Pass; 1 test |
| Fixture generated-tree SEO audit | Pass; 13 HTML pages, 11 sitemap URLs, 0 errors, 0 warnings |
| Production-mode generated-tree SEO audit | Pass; 13 HTML pages, 12 sitemap URLs, 0 errors, 0 warnings |
| npm audit | Pass; 0 vulnerabilities |
| Production robots/sitemap/canonical output | Pass |

Skipped PHP tests require Windows symbolic-link creation privileges unavailable in this environment; the skip reasons are explicit and unrelated to the SEO changes.

## Modified files

- `src/Publishing/SiteBuilder.php` — UTC indexability boundary, sitemap/archive filtering, future-page directives, structured data and internal page context.
- `src/Publishing/PublicationService.php`, `src/Console/Command/TickCommand.php` — date-rollover publication detection.
- `assets/js/main.js` — local-date classification, single error notice, dated/archive labels.
- `assets/js/solver.js` — truthful vocabulary failure/count state.
- `templates/base.html.twig`, `templates/home.html.twig`, `templates/dated.html.twig`, `templates/archive.html.twig`, `templates/partials/archive-list.html.twig`, `templates/solver.html.twig`, `templates/guide.html.twig` — metadata directives, no-JS fallback, truthful labels, content and internal links.
- `resources/content/pages.json` — clearer rules and starting-word headings.
- `scripts/seo-audit.mjs` — intended noindex/canonical/sitemap validation.
- `scripts/keyword-audit.mjs` — complete reproducible 1,000-row mapping without KD/volume-based decisions.
- `docs/SEO_KEYWORD_MAP.md`, `docs/SEO_KEYWORD_COVERAGE.csv`, `docs/SEO_KEYWORD_SUMMARY.md`, this report — strategy and complete evidence.
- `tests/integration/Publishing/PublicationServiceTest.php`, `tests/js/date.test.js`, `tests/js/keyword-audit.test.js`, `tests/browser/smoke.spec.js`, `tests/browser/performance.spec.js` — rollover, timezone, state, no-JS, solver and performance regressions.
- `package.json`, `playwright.config.js` — performance test command/project.
- `assets/dist/manifest.json` and regenerated hashed main/solver bundles — deterministic browser assets.
- Responsive screenshots under `docs/screenshots/` — updated visual baselines.
- `README.md` — linked this implementation report from the project documentation index.
- `BUILD_REPORT.md` — formatting normalization only.

The PRD and production credentials were not modified. The verified release was deployed through the existing Hostinger production configuration.

## Deployment verification

Commit `6d1498c` is live on Hostinger. The production build published the 27 October homepage, kept the 28 October UTC+14 page accessible with `noindex, follow`, and excluded that future date from the sitemap. All 12 sitemap URLs returned `200`, remained self-canonical and indexable, and all 31 web-accessible integrity-manifest files matched their SHA-256 hashes. A real-browser check passed for Pakistan, UTC+14 and the solver with no page, console or request errors. The Hostinger scheduler heartbeat advanced after the release switch, confirming that the automatic publisher continued on the new release.

Search Console ownership/submission and field Core Web Vitals remain external account/data checks.
