# Wordle Hit Today
## Product requirements and implementation specification

**Version:** 1.2 — consolidated baseline, 16 October 2026
**Owner:** Arsalan Baloch
**Audience:** Arsalan Baloch and the coding assistant implementing this project
**Delivery:** Working private/local build, tests, documentation and verified private GitHub push. Public launch is a later action.

This version replaces versions 1.0 and 1.1. It is one consistent specification, not a set of competing additions. MUST means required; SHOULD means expected unless a reason and acceptance impact are documented. Proposed operational targets must be tested; they are not claims about an already completed site.

## 1 Product and architecture decisions

Build an English Wordle-help website with daily progressive hints, controlled answer reveals, a browser solver, historical answers and original supporting guides. Visitors receive the puzzle matching their device's local calendar date. Daily operation should require no routine owner editing when dependencies function normally.

Use a private PHP command-line publishing application and structured JSON records to generate static HTML, CSS and small JavaScript assets. Public requests do not execute PHP, query a database or call the publisher. Runtime data is private; generated pages are replaceable. This architecture follows the read-heavy product requirements; database size alone does not determine performance.

Implement Wordle only. Configuration files manage the site. Build only features described here. Do not reintroduce cancelled features or install a framework solely to fill architectural diagrams. Use small modules and maintained dependencies where they reduce security or maintenance risk.

**Non-negotiable engineering objective:** code MUST be safe, fast, standardized, modular, testable, scalable within measured hosting limits and easy for a different developer to maintain. A working-looking screen without correct data handling, tests and recovery is incomplete.

### Responsibility and limits

The owner has stated that intended NYT use is permitted. Preserve applicable conditions without claiming independent license verification. Browser responses were supplied for 6 and 8 October 2026; hosting access and release behaviour are not yet proven. No guarantee of error-free operation, complete future dictionary coverage, indexing or search rankings is permitted. Failed checks must be visible, not replaced by invented data.

## 2 Pages and URL contract

Use lowercase, HTTPS, a configured canonical host and trailing slashes for content pages. Domain is not yet supplied; private preview may use a labelled example origin. Production builds MUST fail if placeholder origins remain.

| Route | Purpose | Canonical rule |
|---|---|---|
| `/` | Stable today hub, date resolution, hints/answer for a labelled default date, links to dated alternatives | Self |
| `/wordle-solver/` | Solver, instructions and vocabulary coverage note | Self |
| `/wordle-answers/` | Newest-first archive, date/number/word search | Self |
| `/wordle-answers/page/2/` | Additional archive pages, 50 records per page | Self for each substantive page |
| `/wordle-answers/2026/` | Published dates in a year, month navigation | Self |
| `/wordle-answers/2026/10/` | Published dates in a month | Self |
| `/wordle-answers/2026-10-08/` | Fixed historical/date-specific answer and hints | Self |
| `/how-to-play-wordle/` | Original rules and feedback examples | Self |
| `/wordle-strategy/` | Original practical strategy and starting-word guidance | Self |
| `/about/`, `/privacy/` | Methodology, independence, corrections contact, storage disclosures | Self |
| `/404.html` | Helpful missing-page template | Requested missing route must return HTTP 404 |

Do not publish empty years/months or dates without real records. Out-of-range pagination returns 404. Page one aliases redirect to the base archive. Query-string searches do not generate new pages. Ignore tracking parameters when forming canonicals; preserve meaningful visitor navigation without redirect loops.

Header: Wordle Hit Today text brand, Today, Solver, Past Answers, How to Play. Footer: Strategy, About, Privacy, corrections contact and independent-site notice. Link ordinarily to the official game; do not imply that the site hosts the official game. Full clickable navigation must work without JavaScript.

## 3 Local date behaviour and global publishing

### Visitor contract

DT01. Resolve timezone through `Intl.DateTimeFormat().resolvedOptions().timeZone` and the device's current local calendar date. No IP geolocation or location permission is required. Build YYYY-MM-DD from local year/month/day components, NOT `toISOString()`, which represents UTC.

DT02. India on 17 October must see 17 October's puzzle even if a USA visitor is still on 16 October. Language, IP country and publisher-server timezone do not replace the visitor's date.

DT03. Homepage includes a small local-date bootstrap. Until it determines which dated content applies, keep the default answer/hints inaccessible to ordinary visual, keyboard and screen-reader interaction. If local date equals the home page's labelled date, unlock its disclosures. If different, navigate with `location.replace` to that local date's published dated page. Do not add that date to the permanent homepage canonical.

DT04. A dated page is authoritative for its explicit date. Never automatically redirect a visitor away from a deliberately selected historical page. Show a link to the local day's puzzle. A date picker always permits manual override because device clocks can be wrong; remember no override unless explicitly implemented and disclosed.

DT05. An old open tab checks date on visibility return and at least once a minute while active. When local midnight changes, reset spoiler state and show an update action or move to the appropriate date in automatic-today mode. Do not interrupt someone intentionally reading an archive date.

DT06. Without JavaScript, serve useful explicitly UTC-dated homepage content and a date picker/navigation with a notice that automatic local-date selection requires JavaScript. Never claim universal local-date accuracy for that fallback. Do not treat crawlers differently from other clients with identical capabilities.

### Published-date discovery and pending dates

Generate a small public manifest at `/assets/data/published-dates.json`: `schema_version`, `built_at_utc`, `homepage_default_date`, `available_dates` (at most the current globally relevant dates) and a filename-hashed archive index map. It contains dates, numbers and URLs, not future answers or private errors.

The client validates the manifest schema/date/URL origin. If its date is unavailable, show “Hints for {date} are not available yet”, retain navigation and offer a bounded retry. Never substitute another date's answer. Do not repeatedly redirect to a missing dated page. If the manifest fails to load, retain the explicit fallback label and allow manual navigation with an error notice.

The static homepage's default date is UTC for predictable cacheable HTML. This is a fallback only; the reader's resolved local date takes precedence. The old London-wide publication rule is removed.

### Background eligibility

DT07. Determine globally relevant dates from the current instant across the legitimate UTC−12 to UTC+14 calendar range. Fetch dates currently in use somewhere in that range; near date boundaries this may include yesterday, today and tomorrow relative to UTC. An answer for the next UTC date can legitimately be available to a visitor already on that date. Do not block it because London has not reached midnight.

DT08. The earliest publication instant for a date is its midnight at UTC+14. Do not publish an answer before any real timezone has reached that calendar date, even if a source exposes it early. Fetching eligible data is not proof that all regions should see it.

Use an injected UTC clock and timezone-aware calendar operations on the server. Keep source dates separate from timestamps. Tests MUST cover Asia/Kolkata, Asia/Karachi, America/New_York, Pacific/Kiritimati and a UTC−12 zone, DST boundaries, year/leap-day rollover, incorrect device date and missing Intl support.

## 4 Daily hints and answer interaction

FR01. Every answer region shows full date, puzzle number, publication/pending state and a source-check timestamp. Call it “Source checked”; schema validation is not independent confirmation by playing the game.

FR02. Reveal order: vowel count, repeated-letter status, sourced meaning when available, first letter, final letter, optional partial pattern. Count A/E/I/O/U occurrences, including repeats; explicitly explain exclusion of Y.

FR03. Each hint is an independently collapsed native disclosure. It exposes a descriptive label and expanded state. Provide five individually revealed letter tiles and a separate Show full answer control. A new puzzle date starts with all spoilers closed. No-JavaScript visitors can use disclosures once they deliberately select the appropriate dated content.

FR04. The primary flow must not expose the full answer in previews, recent-answer cards, share text, page title or meta description by default. Hidden answers are convenience controls, not secure secrets; HTML inspection can reveal them.

FR05. Do not invent difficulty scores, average guesses, etymology, syllables, editorial meanings or personal playing experiences. Only show sourced facts. Recent answers link to their explicit dates and use controlled spoiler previews.

FR06. Sharing requires a user action and excludes the solution. If clipboard/share support fails, provide selectable spoiler-free text. No unsolicited dialogs or automatic clipboard writes.

### Definitions

Use a pinned Princeton WordNet 3.0 distribution, retaining its license, copyright, disclaimer and required reference with derivatives. Record archive URL, version and checksum in a data manifest. Commercial use is permitted subject to the source license; inspect the exact downloaded terms.

Import local English lemmas, part of speech, sense identifiers and glosses. Exact-form lookup comes first; morphology may resolve a base form only with accurate labelling. Prefer a suitable common sense where data supports it, remove answer-bearing usage examples and reject circular, unsafe or unhelpful definitions. Use “A meaning of this word”; do not claim knowledge of the game editor's intended sense.

Reject a meaning clue that contains the answer or obvious derivatives. Omit the clue if no suitable gloss passes. Missing meanings never block factual letter hints. No guessed or AI-invented definition is allowed. Initial integration must report actual coverage; importing a resource is not a promise that every word has a suitable clue.

## 5 Solver specification

SL01. Up to six five-letter guesses. Each feedback cell starts UNSET, distinct from grey. Feedback choices cycle UNSET → absent/grey → present elsewhere/yellow → correct/green → UNSET, with an accessible direct-choice alternative. Only complete rows with all feedback explicitly assigned enter filtering.

SL02. Accept keyboard, touch, lowercase normalization, five-letter paste and intuitive Backspace. Reject non-ASCII letters, numbers and wrong lengths with useful inline messages. An unknown but syntactically valid guess remains usable with a vocabulary warning.

SL03. For each candidate, compute feedback against each completed guess and compare with supplied feedback. Two passes: allocate exact matches first, then consume remaining answer-letter counts for yellow matches. Never implement grey by blindly banning every occurrence of a repeated letter.

SL04. Default vocabulary is a pinned ESDB/SCOWL-derived general-English five-letter list. Preserve component licenses, retain documented variants, filter proper names/abbreviations using source metadata, deduplicate and sort. Maintain a broader candidate set and common-word ranking metadata. These are not verified complete official NYT lists. Display this limitation briefly, and never claim a suggestion is guaranteed accepted or correct.

SL05. Use a deterministic initial heuristic: score each remaining candidate by the sum of remaining-candidate document frequency of each distinct letter in it; divide by remaining-candidate count for normalized score. Tie-break by available source commonness rank, then uppercase alphabetical order. Unit-test the exact formula. Do not call this entropy-optimal. Keep the daily solution entirely out of scoring inputs; do not remove the actual word from candidates.

SL06. Display candidate count and first 20 suggestions; Load more reveals the next 20 without new indexable URLs. Zero candidates means conflicting feedback or vocabulary limits, not proof that the visitor is wrong. Retain their input and explain both possibilities.

SL07. Lazy-load the compressed dictionary on the solver page only. Show loading, failure/retry and empty states. Profile on representative hardware; use a Worker if needed to avoid blocked interaction. The current daily answer is not separately fetched for the solver. No-JS users see honest instructions, not fake functionality.

SL08. Reset clears guesses, feedback and results. Confirm only when meaningful input will be lost. For the initial release do not persist guesses across visits; this avoids unexpected stale state. A browser reload may reset the tool.

Required independent test examples:

- ALLEY against APPLE → green, yellow, grey, yellow, grey.
- EERIE against LEVEL → yellow, green, grey, grey, grey.
- All-green, all-grey, repeated-letter maximum/minimum counts and contradictory rows.
- Unknown guess allowed, UNSET row excluded, edit/reset/paste, dataset load failure.

## 6 Archive and editorial content

Archive begins with available validated records; do not fabricate a full history. Backfill is an explicit bounded command. Search supports exact ISO date, puzzle number and case-insensitive exact word. Results show all matches where a word occurs on multiple dates. Empty query restores the current archive; invalid query explains accepted formats.

Use a small index manifest and lazy-load year partitions. Exact dates select the year directly; number lookup uses year number ranges; word searches check bounded year chunks with progress and cancellation. Render results with safe text operations. JavaScript search complements ordinary archive links; it is not the only discovery route.

Historical pages contain fixed date/number, controlled reveals, factual clues, available sourced meaning, actual correction note if needed, nearest available previous/next date and archive navigation. Daily home content and history have distinct current-help and historical-lookup roles; do not copy a long unchanged article onto every dated page.

Deliver real original English guides, useful FAQs, About, methodology and Privacy content. No Lorem ipsum, fake claims, fabricated authors or scraped competitor prose. State independence and link to the official game. Contact email and final domain may be labelled placeholders in private preview but must be real before production. Host logs and any selected monitoring are disclosed; do not pretend the site collects nothing if hosting logs exist.

## 7 Design and responsive behaviour

### Approved typography

Headings use **Manrope**, body/control text uses **Inter**. Use locally hosted English/Latin WOFF2 subsets and include their actual license files. Initial weights: Manrope 600/700, Inter 400/600; use a smaller variable-font combination only if measured transfer size is better. Avoid duplicate downloads. Use `font-display: swap`, sensible metric-compatible system fallbacks and explicit line heights. Preload only fonts actually needed above the fold. Check that swapping fonts does not move tiles or controls significantly.

Body starts at 16px/1.6. Headings scale with clamp; H1 approximately 28–44px, H2 22–30px. Puzzle letters use bold fixed-size boxes with predictable dimensions; do not depend on every font glyph having equal advance width. Typography is part of the asset/performance budget, not an exemption from it.

### Visual tokens and components

Initial tokens: background #FAFAF7, body text #17211B, green accent #256D45; subtle neutral borders and surface colours chosen with tested contrast. Use an 8px spacing rhythm with 4px subdivisions, approximately 10px control radius and 14px panel radius. Prefer borders to large shadows. These are first-preview defaults, editable in one token file.

Readability maximum is about 46rem; wider solver/archive regions may reach 72rem. Page side padding: 16px on narrow devices, 24px tablet, 32px desktop. Buttons/tile feedback targets preferably at least 44×44px. Ensure five cells fit at 320px without horizontal overflow. Avoid giant hero banners, autoplay, parallax, unnecessary motion or page-builder-style wrapper layers.

### Page wireframe contract

| Page | Ordered components |
|---|---|
| Home | Compact header; local-date resolution/status; intro; hints; answer tiles; meaning; yesterday/recent links; solver CTA; short help; footer |
| Solver | Header; instructions; input grid; validation; actions; candidate count/results; coverage note; footer |
| Archive | Header; heading; search/progress; year/month navigation; dated results; pagination; footer |
| Dated answer | Header; fixed date/number; hints/reveals; available meaning; correction note; adjacent dates; footer |
| Guide | Header; title/intro; readable content/examples; relevant tool links; footer |

Produce mobile/desktop wireframes, then implement and inspect screenshots before calling the visual design complete. Include pending, missing-meaning, no-results, contradictory feedback, failed-load and long-text states. Native accessible menu disclosures are preferred; mobile navigation must work without a JavaScript-only overlay.

### Accessibility and browser checks

Target WCAG 2.2 AA, without claiming certification from automated scores alone. Semantic landmarks, skip link, one H1, logical headings, visible focus, meaningful labels, text alternatives to colours and correct disclosure state are mandatory. Normal text contrast ≥4.5:1, applicable large text/UI contrast ≥3:1. Respect reduced motion and 200% zoom; manually test reflow at 320 CSS pixels.

Test 320/375/768/1024/1440px, portrait/landscape and long content. Cover stable Chrome, Edge, Firefox and Safari, plus representative Android Chrome and iOS Safari when accessible. Record actual versions/devices and report unavailable tests as blocked. Screen-reader result announcements must not reveal the hidden solution.

## 8 Data contract and validation

Official-domain source pattern: `https://www.nytimes.com/svc/wordle/v2/YYYY-MM-DD.json`. Treat it as a changeable upstream dependency, not a guaranteed supported developer API. Use one bounded HTTPS adapter; public page loads never call it.

Validate HTTP response and content, not status alone. HTML “Site unavailable” with 200 is a failure. Require valid JSON object, exact ISO print_date matching request, solution matching `[A-Za-z]{5}`, non-negative integer days_since_launch and a bounded upstream identifier. Editor is optional escaped text. Normalize solution uppercase. Use a validated date/number anchor to detect anomalies; upstream id is NOT the public puzzle number and need not be sequential.

Implement JSON Schema documents for records, configuration, journal and public manifest, plus additional application-level invariants. Version every schema and test migrations. Unknown upstream fields may be ignored; missing required fields or changed field types fail validation. Reject overlong or deeply nested payloads before expensive processing.

Illustrative private fixture record, not a live publication:

```json
{
  "schema_version": 1,
  "mode": "fixture",
  "puzzle_date": "2026-01-01",
  "puzzle_number": 900001,
  "upstream_id": "fixture-1",
  "solution": "PLANT",
  "source_url": null,
  "fetched_at_utc": null,
  "source_checked_at_utc": null,
  "validation_method": "synthetic_fixture",
  "content_revision": 1,
  "record_state": "validated",
  "hint_values": {"vowel_occurrences": 1, "has_repeats": false},
  "definition_reference": null,
  "content_hash": "<computed SHA-256>"
}
```

Production schema disallows fixture mode/IDs/numbers and requires actual source timestamps/URL. A date received from the device must never become a filesystem path without strict parsing and allowed-root checks.

Source checks establish provenance and structural consistency, not independent gameplay verification. Display “Source checked” accurately. If data conflicts, quarantine; do not invent a replacement answer. Record checksum, source check and revision for corrections.

## 9 Modules and repository layout

```text
bin/wordle                    CLI entry point
config/site.example.json      Validated public/default settings
schemas/                      JSON schemas and migration notes
src/Clock/                    Injected clock and calendar eligibility
src/Source/                   HTTPS source adapter
src/Validation/               Schemas and cross-field invariants
src/Hints/                    Pure clue calculations
src/Definitions/              Licensed gloss import and lookup
src/Storage/                  Versioned records and atomic writes
src/Publishing/               Generator journal locking and promotion
src/Monitoring/               Sanitized status and health checks
src/Console/                  Commands exit codes and orchestration
templates/                    Escaped server-generated HTML
assets/css/                   Tokens and component styles
assets/js/                    Date resolver solver archive modules
resources/                    Original guide copy and licensed manifests
fixtures/                     Synthetic records and error responses
tests/unit/ tests/integration/ tests/browser/
docs/                         Setup configuration deployment recovery
.github/workflows/            CI only until deployment is authorized
composer.json composer.lock package.json package-lock.json
```

Runtime paths MUST be external configuration: private records/journals/locks/backups and separate public output root. Local defaults can use ignored `var/private/` and `var/site/`; only `var/site/` is web-served. No production credentials or runtime data in Git. The document root MUST NOT expose source/resources/vendor/private paths.

Use small interfaces for Clock, SourceClient, RecordStore and Publisher; inject them for tests. Do not build a generic multi-game engine. Keep pure functions for scoring/hints independent of network/filesystem. Route all writes through one storage component. No giant all-in-one script, duplicate rules, magic globals or silently swallowed exceptions.

## 10 Safe fast standardized code requirements

### PHP and front-end conventions

ENG01. Production baseline PHP 8.4+ with supported security updates; verify actual host compatibility and current support before deployment. Use Composer 2, PSR-4 namespaces, PSR-12 formatting, strict_types declarations and parameter/return types. Use readonly value objects where useful, explicit enums/states and typed exceptions. Keep docblocks for shapes/generics and public contracts, not redundant narration.

ENG02. Select maintained test tooling compatible with the baseline: PHPUnit, PHPStan, PHP CS Fixer, ESLint, Prettier and Playwright with axe-core. Pin exact resolved versions in lockfiles; document major-version choices and runtime requirements. PHPStan starts at level 8 or stricter; justified narrow suppressions only. No giant baseline that hides newly written code. Run tools through project dependencies, not unpinned global downloads.

ENG03. Native ES modules with clear JSDoc types or checked JavaScript. Avoid a front-end framework for simple disclosures/date routing. Templates escape output by context; CSS uses one token system and component naming convention. Dependencies require a documented purpose, license and update path. Do not write custom cryptography, HTTP parsers or security utilities when maintained standard facilities exist.

ENG04. Tests target behaviour, edge cases and failure recovery, not snapshots that merely repeat implementation. Use an injected clock, deterministic fixture data and an independent feedback oracle. Fix root causes; do not delete tests or loosen acceptance targets to make CI green without a documented owner decision.

ENG05. Code must be easy to change: single-purpose modules, explicit contracts, consistent names, small reviewable commits and comments explaining non-obvious decisions. Keep examples synchronized with actual commands. Do not overengineer abstractions or add dependencies “for scalability”. Maintain a requirements-to-test checklist.

### Security

SEC01. CLI-only maintenance commands; no public fetch/build/correction endpoint. Secrets live outside Git and web root. Commit an example configuration only. Redact credentials/raw remote responses from logs, reports and CI.

SEC02. HTTPS with certificate verification, allowlisted hosts and redirects, prevention of private-network destinations, bounded response sizes/timeouts and no user-supplied fetch URLs. Defaults: 10-second connection, 20-second total, 64KiB answer response ceiling; dataset imports have separate reviewed limits/checksums.

SEC03. Validate paths against allowed roots; prevent traversal, unsafe symlink destinations and executable deserialization. Use escaped templates and safe DOM text operations, not unchecked innerHTML. Fail safely on invalid JSON, schema drift and malicious upstream text.

SEC04. Least-privilege filesystem/deployment access; no directory listing. Test HTTP denial of source, secrets, logs, backups and dependencies. Production HTTPS, nosniff, tested CSP, Referrer-Policy and Permissions-Policy. Enable HSTS only after domain/HTTPS verification. CSP must allow local fonts and actual required assets without broad unsafe-inline exceptions.

SEC05. Audit dependencies and staged Git files. Resolve high/critical vulnerabilities or document an explicit reviewed exception. Pin CI actions to reviewed immutable revisions. Untrusted pull requests never receive production secrets. No force-push or destructive sync against runtime data.

### Performance and growth

PERF01. Static public HTML; no publisher/definition calls per visitor. Lazy-load solver and archive data, never every historical record on home. Build new/changed records and affected indexes only. Keep year/month storage partitions and 50-row archive pages.

PERF02. Compressed initial homepage assets ≤220KiB including approved font subsets; initial JS ≤30KiB, CSS ≤30KiB. Suggested lazy solver dataset budget ≤250KiB. If fonts exceed budget, optimize subsets/weights before revising targets. Keep layout dimensions stable while fonts/data load.

PERF03. Target field p75 LCP ≤2.5s, INP ≤200ms, CLS ≤0.1 after sufficient production data exists. Prelaunch: median of three documented Lighthouse mobile runs ≥95 Performance and ≥95 Accessibility, no serious/critical automated accessibility findings, plus manual review. Do not claim lab scores prove field results.

PERF04. Cache TTFB target ≤500ms from a specified region; preview capacity target 50 concurrent readers for five minutes, zero application 5xx and p95 ≤1s under documented permitted conditions. Solver filtering target ≤100ms on documented mid-range hardware. Report limits, hardware/cache/network and blockers honestly.

PERF05. Measure file count, inode/disk use, build duration and bytes transferred. Use bounded indexes, incremental generation and log rotation. Change storage/hosting only when measured needs justify it. No promise of infinite traffic or universal millisecond loading.

## 11 Publishing state machine and schedule

One cron scheduler executes a bounded `tick` command every minute if supported. It handles cheap date rollover/heartbeat checks; it MUST NOT fetch each minute merely because it was invoked. Source requests for each pending eligible date run at most once per five minutes, subject to backoff. Document larger cron minimum intervals and their actual freshness consequences.

| Condition | Action |
|---|---|
| No job active | Acquire stable exclusive lock outside release directories |
| Pending eligible date | Fetch when next_attempt_at permits |
| Published record | Recheck about 1h and 6h later and once the next day |
| Transient failure | Persist retries for 5, 15, 30, 60 minutes, then hourly |
| 429/Retry-After | Respect source delay and minimum interval |
| 401/403 or denied access | Stop aggressive retries; report blocked integration |
| Invalid/conflicting payload | Quarantine diagnostics/revision; retain dated records |
| Downtime recovery | Process globally relevant dates and bounded missed records; separate backfill for large gaps |

State progression: fetched → validated → staged → published, with failed/quarantined side states. Record state and published-artifact state are distinct. A build journal records run ID, record revision, planned files/checksums, step and error class before promotion. Schema example:

```json
{"schema_version":1,"run_id":"<unique>","mode":"fixture","record_date":"2026-01-01","record_revision":1,"stage":"staged","files":[{"path":"wordle-answers/2026-01-01/index.html","sha256":"<computed>"}],"promoted":false}
```

Generate in a staging directory. Validate dates, links, metadata, licenses and hashes. Publish dated records and compatible versioned assets first, then indexes/manifest/home and sitemap. Prefer atomic release-pointer promotion where supported. Otherwise use same-filesystem temporary-file-and-rename replacements, ensuring every intermediate page is valid; separate file renames are not one transaction. Retain rollback data and assets before replacement. Mark published only after verification.

On interruption, reconcile journal and actual files/checksums before proceeding. All deployments/backfill/corrections share the same stable lock; never unlink it while another process may hold it. Disk-full, permissions and process termination tests are mandatory. A quarantined date must not prevent another valid date from publishing.

Correction command requires date, expected revision, dry-run diff and confirmed source evidence. Keep previous record revision and show an actual correction notice. Never silently change answers or pretend old content is fresh. No simultaneous duplicate writers or unlimited historical refetches.

Target normal publication within ten minutes of the eligible source returning valid data, subject to measured polling/host constraints. Alert when delayed; do not promise a release time before testing upstream availability.

## 12 Cache and failure behaviour

| Resource | Default policy |
|---|---|
| Home and published-date manifest | Browser revalidation; shared cache at most 60s, tested purge/expiry |
| Historical HTML | max-age=300, must-revalidate; purge/revalidate corrections |
| Archives and sitemap | max-age=60, must-revalidate |
| Filename-hashed CSS JS fonts dictionaries | max-age=31536000, immutable; retain referenced rollback assets |
| Monitoring status | no-store; sanitized fields only |
| Private records/config/source/logs | Never publicly served |

Use ETag/Last-Modified where host supports them; document actual server/CDN headers. No claim of a purge API before testing it. Existing open tabs use local-date checks; browser date does not justify downloading a different answer without clear date state.

| Failure | Visitor experience | Program behaviour |
|---|---|---|
| Answer not available | Explicit local date pending, no substitute answer | Bounded retry/backoff |
| Source denied/unavailable | Pending or previously published explicitly dated content | Classify error, monitor, no bypass |
| Definition missing | Other clues and answer work; meaning omitted | Record coverage gap |
| Solver asset failure | Retry and explanation; other pages work | No fabricated results |
| Conflicting feedback | Retained input, zero candidates and explanation | Deterministic filtering |
| Low disk/corrupt file/build failure | Last valid dated pages stay usable | Abort promotion, preserve journal and alert |
| Date manifest failure | Labelled fallback plus manual date links | Bounded client retry; no redirect loop |
| Whole-site technical outage | Proper short-lived 503 if host permits | Recovery runbook; not permanent noindex |

A useful homepage pending a new answer remains HTTP 200, self-canonical and indexable. Unpublished dated routes return 404 and stay out of sitemap. Do not temporarily noindex the established homepage. Robots rules do not protect private previews; use actual access restriction.

## 13 Monitoring backup and maintenance

Write rotated sanitized structured logs: run ID, eligible date, stage, result, retry count, duration and public publication time. Default retention 30 days with size cap. Heartbeat must update on a successful scheduler tick even if no source fetch is due; source success timestamp is separate.

Before production configure an independent monitor outside hosting cron. It checks availability, public status vs published pages, manifest eligibility and job heartbeat. Defaults: alert on no heartbeat for 15 minutes or eligible date pending for 30 minutes; tune from observed source behaviour to avoid false alarms. One incident alert, bounded reminders and recovery notice. Owner notification destination and actual monitor connection must be tested, not assumed. Local delivery can complete before these hosting dependencies exist.

Daily backup private records, definitions/corrections, configuration excluding unprotected secrets, journal and manifests. Keep seven daily/four weekly copies subject to limits and at least one encrypted off-server copy under owner control. Encryption keys must not exist only on the failed server. Source Git history is not a runtime backup.

Restore procedure: stop/lock writers, verify backup checksum, restore into a new private path, validate schemas, rebuild all public output, smoke-test, promote and resume jobs. Test one-record and full-site recovery. Targets: recovery point ≤24h and recovery ≤4h after maintainer response begins; not an uptime/support guarantee.

Weekly or appropriately scheduled maintenance checks dependencies, logs, backup integrity, dictionary corrections and resource trends. Content updates remain automatic; unusual source/data changes can need maintenance. Never delete canonical records automatically to free space. Reserve greater of 100MiB or twice staged write size before promotion and cap run time below host limits.

## 14 SEO and content plan

SEO01. Ready English HTML, lang=en, one meaningful H1, logical headings, unique relevant title/description and absolute canonical. Default home title: `Wordle Hint Today and Answer for {date} | Wordle Hit Today`. Visible default date must match metadata; client local-date enhancement links to the correctly dated page. Never claim that a single cached HTML title reflects every visitor's date simultaneously.

SEO02. Historical title: `Wordle #{number} Answer and Hints for {full date} | Wordle Hit Today`. Solver: `Wordle Solver and Five Letter Word Helper | Wordle Hit Today`. Metadata omits the answer by default. Structured data uses actual content/dates; no fake ratings or FAQ/Game rich-result promises. WebSite, truthful Organization and appropriate BreadcrumbList are reasonable; Article only for real editorial articles.

SEO03. Generate sitemap from published canonical pages, truthful lastmod and split at protocol limits. Keep the permanent homepage listed during pending states; exclude unavailable dates, fixture output and search variants. Stable dated pages are the globally unambiguous SEO landing pages. Do not block crawlers from seeing equivalent visible content or build different crawler responses.

SEO04. Normalize host/HTTPS/trailing slash with one redirect, use real 404/503 statuses, avoid parameter page generation and canonicalize each substantive pagination page to itself. Test all generated internal links and schema. No keyword meta tag, hidden text, stuffed footer or repeated near-identical keyword pages.

SEO05. Publish useful original short home help, rules and strategy guides. No rigid word-count ranking quota; remove filler. Archive records preserve real puzzle-specific facts. No index/ranking promise. After authorized launch, configure Search Console, verify ownership, submit sitemap and review actual coverage; credentials and DNS access are separate launch tasks.

### Supplied keyword coverage

Exact duplicates are consolidated; spelling/apostrophe variants share intent. No fabricated search-volume or difficulty data.

| Destination | Supplied terms and treatment |
|---|---|
| Home hints | wordle hint; wordle hint today; todays wordle hint; wordle today hint; today's wordle hint; today wordle hint; wordle clue; wordle clue today; nyt wordle hint; nyt wordle hint today; wordle hints; hint for today's wordle; wordle hints today |
| Home answers | wordle today; wordle today Answer; wordle answer; wordle of the day; wordle answer today; today wordle answers; nyt wordle today; what is the wordle today; today's wordle answer; today wordle answer; wordle word today; wordle for today |
| Solver/help | wordle solver; wordle help; add natural Wordle helper, five-letter word solver and repeated-letter help wording |
| Rules/official-game attribution | wordle nyt; new york times wordle; nytimes wordle; wordle game; wordle new york times; wordle online; explain accurately and link to official play |
| Context only | today; wordle ny; wordle uk; do not create vague or duplicate landing pages or claim a separate official UK answer |
| Different publisher intent | wordle hint today mashable; mashable wordle hint today; do not impersonate or force these into copy |
| Different game/product intent | wordle unlimited; wordle strands; wordle connections; do not target through misleading unsupported features |

Additional relevant archive/guide phrases: past Wordle answers, Wordle answer archive, yesterday's Wordle answer, Wordle answer {date}, Wordle #{number}, how to play Wordle, Wordle colour meanings and Wordle starting words. Use natural English; not every supplied term belongs in public copy.

## 15 Configuration and schema example

```json
{
  "schema_version": 1,
  "mode": "fixture",
  "site_name": "Wordle Hit Today",
  "canonical_origin": "https://example.invalid",
  "homepage_fallback_timezone": "UTC",
  "visitor_date_mode": "device_local",
  "source_poll_minutes": 5,
  "archive_page_size": 50,
  "public_output": "var/site",
  "private_storage": "var/private",
  "source": {"host": "www.nytimes.com", "path_pattern": "/svc/wordle/v2/{date}.json"},
  "contact_address": null
}
```

This is a contract example to implement, not proof that commands/config already exist. Production validation requires real canonical_origin/contact, absolute safe storage paths, eligible-source configuration and no fixture settings. JSON holds no secrets; environment variables or a private ignored file supply credentials. Explicit CLI flags override only allowlisted settings and cannot silently flip fixture/live mode.

Provide validated fields for timeout/retry, monitoring status, backup paths and theme token settings. Document all defaults and change impact. Domain change rebuilds canonicals, sitemap, structured data and internal absolute links; it does not change historical record identity. Versioned migrations must preserve old data or fail with recovery instructions.

## 16 Local setup and command contract

The coding assistant MUST inspect the actual OS, workspace, existing Git remotes and installed tools first. The owner's Windows/Cursor environment is distinct from this chat runtime. Reuse suitable installed tools; do not overwrite unrelated projects. Use official downloads/registries, explain missing prerequisites and never request passwords in chat.

Development requires supported PHP 8.4+, Composer 2, Git and a Node.js LTS runtime only for asset tooling/browser tests. MySQL is unnecessary. PHP needs cURL, JSON, OpenSSL and locking/filesystem capabilities; PHPUnit may need additional documented extensions. Pin actual tested versions in README/CI. Production needs only PHP CLI for jobs and static web serving plus required extensions.

The implementation MUST supply these commands, or adjust every example and README consistently before handoff:

```sh
composer install
npm ci
php bin/wordle doctor --mode=fixture
php bin/wordle build-fixtures
npm run build
php bin/wordle build --mode=fixture
php bin/wordle preview --host=127.0.0.1 --port=8080
```

`build-fixtures` creates synthetic private records only. `npm run build` produces versioned frontend assets; `build` combines assets/templates/records into public output. Preview must use a provided router that handles content URLs, headers and real status codes, not a catch-all 200 server. Stop with Ctrl+C. Return the exact reachable browser URL. Fixture preview is clearly marked DEMO; production builds reject demo content.

Use Composer scripts: `composer format:check`, `composer analyse`, `composer test`, `composer audit`. Use npm scripts: `npm run lint`, `npm run format:check`, `npm run build`, `npm run test:unit`, `npm run test:browser`, `npm run test:a11y`, `npm run test:seo`. Include an aggregate check command and actual tool configuration. Test tools are development-only, excluded from public output.

CLI command surface: doctor, build-fixtures, import-vocabulary, import-definitions, fetch-date, build, tick, backfill, correct, reconcile, status, backup, restore and rollback. Each documents flags, external requests, dry-run, idempotency and exit codes. Use 0 for success, distinct nonzero categories for invalid input/config, blocked source/network and storage/build failures. Contended lock is a logged safe skip, not a broken publication.

## 17 GitHub and eventual deployment

1. Check authenticated GitHub identity and remotes. If absent, guide secure device/browser login. Do not print tokens or request passwords.
2. Resolve the intended repository/account from verified context; ask only if ambiguous. Create/connect a PRIVATE repository. Do not change unrelated origin or publish it publicly.
3. Add gitignore before staging; exclude credentials, runtime records, backups, logs, local generated output and tool caches. Include licensed development data only where redistribution permits.
4. Commit in logical tested units on a branch. Review staged diff and scan secrets. Do not force-push. Enable main protection if supported by the owner's GitHub plan; otherwise document procedural checks honestly without purchasing upgrades.
5. CI runs lint, static analysis, unit/integration/browser/SEO/accessibility checks, deterministic fixture build and audits. Pin actions and prevent untrusted PR access to secrets. Record quotas/unavailable capabilities; run equivalent local checks rather than claim unsupported enforcement.
6. Push passing reviewed source. Verify repository visibility and remote commit SHA. Return URL, branch, SHA and real CI/local results. Authentication failure is a blocker, not successful handoff.

Production deployment remains later. Deliver a documented deploy adapter for the selected host, not speculative credentials. Acquire the shared publication lock, preserve external runtime storage, test schema compatibility, stage the release, rebuild affected pages, validate, promote and verify. Use least-privilege SSH/SFTP or supported host Git deployment with host identity checks. Do not run destructive synchronization on shared runtime paths.

Daily publication happens through hosting cron without a Git commit. Deployment rollback restores code plus a compatible manifest and regenerates content from authoritative records where necessary. Never roll a code release back by overwriting newer valid runtime records. No public launch occurs merely because implementation passes checks.

## 18 Acceptance and test matrix

| ID | Required evidence |
|---|---|
| AT01 Data | Valid fixture accepted; HTML 200, bad JSON, wrong date, malformed solution, ID/number confusion and source denial rejected |
| AT02 Local date | India vs USA simultaneously different dates; UTC+14/UTC−12 range; DST/leap/year rollover; device override; no-JS fallback; no wrong-date answer flash |
| AT03 Hints | Deterministic counts/repeats; disclosures independent; reset on new date; missing clue omitted; no fabricated statistics |
| AT04 Solver | Independent two-pass oracle; duplicates; unset rows; unknown guess; conflicts; paste/edit/reset; deterministic ranking; delayed/failed asset load |
| AT05 Archive | Search all exact modes; repeated word dates; missing dates; index partition loading; pagination/year/month links; bounded load |
| AT06 Publishing | Idempotency; contention; corrections; backoff; crash each stage; journal reconcile; cache/manifest ordering; low disk; no truncated files |
| AT07 Security | HTML/path injection, disallowed redirect, private paths denied, CLI-only maintenance, TLS checks, secret/dependency scans |
| AT08 SEO | Canonicals/status/metadata consistent; pending home indexable; unavailable dates excluded; local-date URLs crawlable; no parameter explosion; staging private |
| AT09 Design | Approved fonts/tokens, responsive screenshots, keyboard/screen-reader/zoom, mobile menu, reduced motion, layout/font swap stability |
| AT10 Performance | Asset budgets, three-run lab medians, solver timing, documented permitted load test and actual limitations |
| AT11 Operations | Independent stale heartbeat alert/recovery; bounded retries; log rotation; off-server backup and tested restore |
| AT12 Delivery | Fresh checkout reproduces build; complete commands/docs; private remote and SHA verified; no unrequested launch |

Maintain REQUIREMENTS.md mapping FR/DT/SL/ENG/SEC/PERF/SEO and operational requirements to files, tests and evidence. PASS, FAIL and BLOCKED are distinct. Do not satisfy production integration using only synthetic fixtures or a sample-only dictionary. Record tested versions/hardware and scripts, not a general “all tests passed” assertion.

## 19 Implementation sequence and handoff

1. **Foundation:** inspect environment; initialize repository/config/schemas; wire tooling and fixture build; document local commands.
2. **Design:** make mobile/desktop wireframes and approved typography; implement page templates and real supporting copy.
3. **Reader functionality:** hints/reveals, local-date resolver, solver, archives and all error/loading states.
4. **Background system:** licensed data import, publisher adapter, validation, state machine, global-date eligibility, schedule, logs and recovery.
5. **Quality:** run full checks, fix issues, inspect browser screenshots, benchmark, review dependency/license inventory.
6. **Private delivery:** verify working preview, reproducibility and GitHub push; list hosting-specific blockers separately.
7. **Later launch:** configure actual host/domain/cron/monitoring/backups; test live retrieval and date availability; test rollback; owner authorizes public promotion.

The previously suggested 14-day live observation is advisory, not a mandatory delay on code/GitHub completion. Report actual observed successful/failed releases; sufficient operational evidence and owner acceptance determine launch readiness.

Deliver README, this PRD, complete source and lockfiles, example configuration, schemas, licensed data manifests/notices, tests/fixtures, wireframes/screenshots, REQUIREMENTS checklist, BUILD_REPORT, CHANGELOG and operating guides. BUILD_REPORT lists exact commands/results, blocked checks, environment, dictionary counts/coverage and repository/commit. Provide setup, configuration, source/date policy, correction, backup/restore and deployment guides. No pseudo-code-only implementation or unfinished controls.

Remaining external configuration: actual domain/contact, GitHub identity/repository, chosen host access/limits, monitor alert destination and backup storage. The dictionary release must be acquired/imported and tested; the source's live regional-date availability must be observed. These do not justify guessing results or blocking all private feature development.

## 20 References and version record

Version 1.2 consolidates earlier instructions, replaces London-wide date selection with device-local date routing and global-date publishing, applies Manrope/Inter, specifies tools/commands/schemas/ranking and adds actionable operational and quality acceptance. No production integration has been claimed complete by writing this document.

Use current primary documentation and record actual resolved versions:

- PHP support: https://www.php.net/supported-versions.php
- PHP conventions: https://www.php-fig.org/psr/psr-12/ and https://www.php-fig.org/psr/psr-4/
- Composer: https://getcomposer.org/doc/
- PHPUnit: https://docs.phpunit.de/
- PHPStan: https://phpstan.org/user-guide/getting-started
- Playwright: https://playwright.dev/docs/intro
- WordNet license: https://wordnet.princeton.edu/license-and-commercial-use
- ESDB source/licenses: https://github.com/en-wl/wordlist
- Accessibility: https://www.w3.org/TR/WCAG22/
- Performance: https://web.dev/articles/vitals
- Canonicals: https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls
- Sitemaps: https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap
- Security verification: https://owasp.org/www-project-application-security-verification-standard/

A specific download checksum, tested coverage or implemented configuration must be recorded during development. Do not invent these values to make documentation appear finished. This specification reduces ambiguity but does not guarantee that no future issue can exist; implementation evidence is required.
