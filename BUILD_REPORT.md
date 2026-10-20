# Build report

## Result

The private fixture build is functional and reproducible locally. It generated 13 HTML pages and 29 public files. Automated PHP, JavaScript, browser, accessibility, SEO, audit, backup, restore, reconciliation, and fixture-publishing checks passed. Production readiness is not claimed because the production datasets, live source observation, hosted performance checks, GitHub delivery, and hosting configuration remain external.

## Environment

- Date: 2026-10-16, Asia/Karachi
- OS visible to the sandbox: Microsoft Windows NT 10.0.19045.0
- PHP: 8.5.11
- Composer: 2.10.3 (project-portable executable)
- Node.js: v24.21.0
- npm: 11.19.0
- Git: 2.56.0.windows.1
- Playwright: 1.64.1
- Chromium: 156.0.8078.4
- CPU/RAM inventory: BLOCKED because the managed Windows environment denied CIM hardware queries

## Verified commands and results

| Command/workflow                      | Result                                                                                                                                  |
| ------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------- |
| `composer check` equivalents          | PHP CS Fixer clean; PHPStan clean; PHPUnit 27 tests / 46 assertions; locked dependency audit found no advisories                        |
| `npm run check` components            | ESLint, Prettier, secret scan, asset build, Vitest, browser, accessibility, and SEO suites passed; npm audit reported 0 vulnerabilities |
| `npm run test:unit`                   | 2 files / 8 tests passed                                                                                                                |
| `npm run test:browser`                | 12 Chromium tests passed across smoke and responsive projects                                                                           |
| `npm run test:a11y`                   | 4 axe checks passed with no serious or critical violations                                                                              |
| `npm run test:seo`                    | 5 SEO checks passed                                                                                                                     |
| `php bin/wordle build --mode=fixture` | 13 pages generated                                                                                                                      |
| Fixture tick/status                   | Publication completed; heartbeat/status JSON updated                                                                                    |
| Backup/restore                        | Checksummed backup created; verification and restore to a new ignored path succeeded for 3 records                                      |
| Reconcile                             | 29 published files reconciled successfully                                                                                              |
| Rollback                              | Rollback candidate inspected without applying a destructive change                                                                      |

## Build measurements

- Generated public files: 29
- Generated pages: 13
- Total generated fixture output: 209,326 bytes
- CSS bundle: 12,154 bytes
- Main JavaScript: 4,187 bytes
- Solver JavaScript: 4,159 bytes
- Archive JavaScript: 1,743 bytes
- Fixture vocabulary asset: 1,331 bytes
- Homepage initial CSS/JS/fonts measured locally: 92,841 bytes
- PHP fixture publication observed locally: approximately 3.9 seconds
- Responsive screenshots: 320, 375, 768, 1024, and 1440 CSS pixels

## Dataset and definition coverage

- Fixture records: 3 synthetic dated records
- Fixture solver candidates: 43
- Fixture definition references: 0 of 3, deliberately omitted rather than fabricated
- Production vocabulary: BLOCKED pending acquisition/import of a pinned licensed release
- Production meaning coverage: BLOCKED pending licensed source import and coverage report

## Repository and delivery

- Local repository branch: `main`
- Local commit: not yet created
- GitHub remote: BLOCKED; no authenticated GitHub account or destination repository is configured
- Private hosted preview: project allocated, but source upload/deployment is BLOCKED pending explicit owner authorization
- Production/public launch: intentionally not performed

## Blocked checks

- Safari/iOS, Android device, manual screen-reader, and physical zoom testing
- Three-run hosted Lighthouse medians and five-minute 50-reader load test
- Low-disk and forced-crash-at-every-stage fault injection
- Independent stale-heartbeat alert delivery and off-server backup verification
- Live source regional-date observation and production dataset coverage
- Real domain, contact email, hosting, scheduler, and TLS verification
- Private GitHub push and remote SHA verification

See `REQUIREMENTS.md` for the requirement-level disposition and the guides under `docs/` for setup, security, deployment, operations, source policy, and recovery.
