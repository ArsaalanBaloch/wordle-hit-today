# Build report

## Result

The maintainability, security, performance, publication-integrity, and recovery audit is complete for the private fixture and production scopes. The generated site is reproducible, the exact tree reconciles with its journal, rollback artifacts carry their own integrity manifest, and CLI backup/restore drills completed successfully.

The production site is live at `https://wordlehintoday.com/` with apex-only DNS, TLS, HTTPS redirection, validated production content, and active automatic publication plus daily/weekly on-host backup schedules. Independent monitoring, physical-device/hosted performance evidence, a working branded mailbox, and encrypted immutable off-server backups remain.

## Environment

- Final local verification: 2026-10-26, Asia/Karachi
- OS: Microsoft Windows NT 10.0.19045.0
- PHP: 8.5.11 (project baseline remains PHP 8.4+)
- Composer: 2.10.3
- Node.js: v24.21.0
- npm: 11.19.0
- Git: 2.56.0.windows.1
- Playwright: 1.64.1
- Chromium: 156.0.8078.4
- CPU/RAM inventory: unavailable because the managed environment denied CIM hardware queries

## Verification

| Check                         | Result                                                                                                                                  |
| ----------------------------- | --------------------------------------------------------------------------------------------------------------------------------------- |
| PHP CS Fixer                  | PASS, 75 PHP files clean                                                                                                                |
| PHPStan                       | PASS, level 8, zero errors                                                                                                              |
| PHPUnit                       | PASS, 69 tests and 149 assertions; 2 Windows symlink tests skipped                                                                      |
| Composer validation/audit     | PASS; lockfile valid and no known advisories                                                                                            |
| ESLint / Prettier             | PASS                                                                                                                                    |
| Secret scan                   | PASS across 165 text-readable tracked/untracked non-ignored files                                                                       |
| Vitest                        | PASS, 19/19                                                                                                                             |
| Playwright browser/responsive | PASS, 14/14                                                                                                                             |
| Playwright accessibility      | PASS, 4/4                                                                                                                               |
| Playwright SEO                | PASS, 8/8                                                                                                                               |
| Dependency audit              | PASS; Composer reported no advisories and npm reported 0 vulnerabilities                                                                |
| `doctor --mode=fixture`       | PASS                                                                                                                                    |
| Fixture generation            | PASS, 3 validated synthetic records                                                                                                     |
| Asset reproducibility         | PASS across consecutive builds; an intentionally failed build preserved the previous `assets/dist` tree and left no staging directories |
| Fixture publication           | PASS, 13 HTML pages and 32 total public files                                                                                           |
| Production publication        | PASS on `https://wordlehintoday.com/`, 13 HTML pages and 32 total public files                                                           |
| Reconciliation                | PASS, all 32 files matched exactly                                                                                                      |
| Rollback inspection           | PASS against the retained build's self-contained integrity manifest                                                                     |
| Backup/restore drill          | PASS; 6 files verified and 3 records restored/schema-validated into a new path                                                          |
| GitHub CI                     | PASS for audit commit `e71fbc3`; all 13 validate-job steps completed successfully in run 39941359312                                    |

## Build measurements

- Generated fixture public tree: 227,679 bytes across 32 files
- Generated production public tree: 426,385 bytes across 32 files
- Homepage initial local assets: 54,390 uncompressed bytes (CSS, main JS, Inter 400, and Manrope 700)
- CSS: 11,992 bytes
- Main JavaScript: 4,522 bytes
- Solver JavaScript: 4,636 bytes
- Archive JavaScript: 3,493 bytes
- Fixture vocabulary: 1,331 bytes
- Production vocabulary: 6,982 words; SHA-256 `cf5139dbaf76957e37826ad6ef9d8d2c634f971e9618ec86da26bd91941dd64d`
- Production definitions: 12,400 safe imported senses from 13,838 prepared rows; SHA-256 `8a9fe6f85abbdc3c98f2b7bee453227a84d93cfe95698e3a2b6582e04b559aa4`
- Measured fixture PHP publication: 1,472 ms
- Responsive screenshots: 320, 375, 768, 1024, and 1440 CSS pixels
- Hosted three-run Lighthouse medians and five-minute 50-reader load test: BLOCKED pending a reachable production-like host

## Dataset and definition coverage

- Fixture records: 3 synthetic dated records
- Fixture solver candidates: 43
- Fixture definition references: 0 of 3, deliberately omitted rather than fabricated
- Production vocabulary: PASS, 6,982 ordered five-letter entries from pinned ESDB 2026.02.25 data
- Production meaning import: PASS, 12,400 safe definitions imported and 1,438 unsafe/unsupported rows omitted
- Initial live-record meaning coverage: 2 of 3 records have a definition reference; absence is displayed honestly

## Repository and delivery

- Branch: `main`
- Remote: `https://github.com/ArsaalanBaloch/wordle-hit-today.git`
- The exact final local and matching remote SHA are reported in the delivery handoff because a commit cannot embed its own SHA.
- Git transport is functional and the repository visibility was verified as `PRIVATE` on 2026-10-23; deployment does not depend on the stale local GitHub CLI token.
- Audit CI: `https://github.com/ArsaalanBaloch/wordle-hit-today/actions/runs/39941359312` completed successfully.
- Production URL: `https://wordlehintoday.com/`; homepage, dated page, robots, sitemap, canonical metadata, security headers, and real 404 behavior were verified after launch.
- A verified migration backup was created before the free-domain website was removed. Generated public directories are normalized to `0755` and files to `0644` before every atomic promotion.

## Integrity scope

- Asset publication validates the manifest from the staged snapshot, exact file set, safe paths, content-addressed filenames, and hashes before public promotion.
- Publication writes a durable `staged` journal before promotion, then `published` after success or `failed` with the promotion state after an error. A private-record-set hash makes interrupted publication retryable.
- Public builds include a self-contained exact-tree integrity manifest. Rollback revalidates it under the publisher lock before swapping directories.
- Reconciliation reads the journal and public tree under the same lock and rejects missing, changed, injected, duplicate, unsafe, or symbolic-link entries.
- Backup creation compares the staged copy with pre-copy source hashes. Verification checks the exact tree, and restore rechecks the staged target before promotion.
- SHA-256 manifests detect corruption relative to the supplied manifest. They are not signatures and do not prove authenticity if an attacker can replace both data and manifest.

## External production-readiness blockers

- Branded contact mailbox and independent monitoring
- Live multi-date/regional source observation beyond the verified 24–27 October 2026 window
- Safari/iOS, Android, manual screen-reader, and 200% zoom testing
- Hosted Lighthouse and permitted load testing
- Forced process termination at every boundary and low-disk fault injection
- Independent stale-heartbeat alerts and encrypted immutable off-server backup/full-restore evidence

See `REQUIREMENTS.md` for requirement-level disposition and `docs/` for setup, security, deployment, operations, source policy, SEO, and recovery guidance.
