# Build report

## Result

The maintainability, security, performance, publication-integrity, and recovery audit is complete for the private fixture scope. The generated site is reproducible, the exact public tree reconciles with its journal, rollback artifacts carry their own integrity manifest, and a CLI backup/restore drill completed successfully.

Production readiness is not claimed. The production domain has been acquired and a noindex Hostinger staging environment is verified, but production still requires licensed vocabulary/definition data, live source/date observation, branded contact email, production DNS/TLS activation, physical-device and hosted performance evidence, independent monitoring, and encrypted immutable off-server backups.

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
| Secret scan                   | PASS across 160 text-readable tracked/untracked non-ignored files                                                                       |
| Vitest                        | PASS, 15/15                                                                                                                             |
| Playwright browser/responsive | PASS, 14/14                                                                                                                             |
| Playwright accessibility      | PASS, 4/4                                                                                                                               |
| Playwright SEO                | PASS, 8/8                                                                                                                               |
| Dependency audit              | PASS; Composer reported no advisories and npm reported 0 vulnerabilities                                                                |
| `doctor --mode=fixture`       | PASS                                                                                                                                    |
| Fixture generation            | PASS, 3 validated synthetic records                                                                                                     |
| Asset reproducibility         | PASS across consecutive builds; an intentionally failed build preserved the previous `assets/dist` tree and left no staging directories |
| Fixture publication           | PASS, 13 HTML pages and 30 total public files                                                                                           |
| Reconciliation                | PASS, all 30 files matched exactly                                                                                                      |
| Rollback inspection           | PASS against the retained build's self-contained integrity manifest                                                                     |
| Backup/restore drill          | PASS; 6 files verified and 3 records restored/schema-validated into a new path                                                          |
| GitHub CI                     | PASS for audit commit `e71fbc3`; all 13 validate-job steps completed successfully in run 39941359312                                    |

## Build measurements

- Generated public tree: 223,620 bytes across 30 files
- Homepage initial local assets: 54,390 uncompressed bytes (CSS, main JS, Inter 400, and Manrope 700)
- CSS: 11,992 bytes
- Main JavaScript: 4,522 bytes
- Solver JavaScript: 4,643 bytes
- Archive JavaScript: 3,493 bytes
- Fixture vocabulary: 1,331 bytes
- Measured fixture PHP publication: 1,472 ms
- Responsive screenshots: 320, 375, 768, 1024, and 1440 CSS pixels
- Hosted three-run Lighthouse medians and five-minute 50-reader load test: BLOCKED pending a reachable production-like host

## Dataset and definition coverage

- Fixture records: 3 synthetic dated records
- Fixture solver candidates: 43
- Fixture definition references: 0 of 3, deliberately omitted rather than fabricated
- Production vocabulary: BLOCKED pending acquisition/import of a pinned licensed release
- Production meaning coverage: BLOCKED pending licensed source import and coverage reporting

## Repository and delivery

- Branch: `main`
- Remote: `https://github.com/ArsaalanBaloch/wordle-hit-today.git`
- The exact final local and matching remote SHA are reported in the delivery handoff because a commit cannot embed its own SHA.
- Git transport is functional and the repository visibility was verified as `PRIVATE` on 2026-10-23; deployment does not depend on the stale local GitHub CLI token.
- Audit CI: `https://github.com/ArsaalanBaloch/wordle-hit-today/actions/runs/39941359312` completed successfully.
- Verified noindex Hostinger staging preview: `https://aqua-turkey-495051.hostingersite.com/`.
- Production/public launch was intentionally not performed.

## Integrity scope

- Asset publication validates the manifest from the staged snapshot, exact file set, safe paths, content-addressed filenames, and hashes before public promotion.
- Publication writes a durable `staged` journal before promotion, then `published` after success or `failed` with the promotion state after an error. A private-record-set hash makes interrupted publication retryable.
- Public builds include a self-contained exact-tree integrity manifest. Rollback revalidates it under the publisher lock before swapping directories.
- Reconciliation reads the journal and public tree under the same lock and rejects missing, changed, injected, duplicate, unsafe, or symbolic-link entries.
- Backup creation compares the staged copy with pre-copy source hashes. Verification checks the exact tree, and restore rechecks the staged target before promotion.
- SHA-256 manifests detect corruption relative to the supplied manifest. They are not signatures and do not prove authenticity if an attacker can replace both data and manifest.

## External production-readiness blockers

- Branded contact email, production-domain DNS/TLS activation, scheduler, monitoring and production document-root verification
- Licensed production vocabulary and definitions with checksums and coverage report
- Live multi-date/regional source observation
- Safari/iOS, Android, manual screen-reader, and 200% zoom testing
- Hosted Lighthouse and permitted load testing
- Forced process termination at every boundary and low-disk fault injection
- Independent stale-heartbeat alerts and encrypted immutable off-server backup/full-restore evidence

See `REQUIREMENTS.md` for requirement-level disposition and `docs/` for setup, security, deployment, operations, source policy, SEO, and recovery guidance.
