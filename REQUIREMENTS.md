# Requirements verification

This checklist records the current private-build evidence. `PASS` means the implemented scope has repeatable evidence; `BLOCKED` means an external service, production dataset, device, or permission is still required. Fixture evidence is never presented as production integration.

## Acceptance matrix

| ID               | Status  | Implementation and evidence                                                                                                                                                                                                                                                                       |
| ---------------- | ------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| AT01 Data        | PASS    | Strict source and record validation in `src/Source`, `src/Validation`, and their PHPUnit tests covers valid fixtures, malformed JSON, date/solution errors, source denial, and puzzle ID/number separation.                                                                                       |
| AT02 Local date  | PASS    | `assets/js/date.js`, Vitest date cases, and browser smoke tests cover timezone selection, overrides, fallback labeling, and avoiding an incorrect answer flash. Dedicated physical-device DST/International Date Line testing remains part of AT09.                                               |
| AT03 Hints       | PASS    | `HintCalculator`, unit tests, independent disclosure controls, and browser tests verify deterministic letter/repeat facts and date-change reset. Fixture records intentionally omit meanings rather than inventing them.                                                                          |
| AT04 Solver      | PASS    | PHP and JavaScript feedback tests cover repeated letters and ranking; browser tests cover editing, reset, conflicts, delayed data, and fixture candidate filtering. Production vocabulary integration is separately BLOCKED below.                                                                |
| AT05 Archive     | PASS    | Generated archive pages and browser tests cover search modes, paging links, missing results, and bounded fixture index loading.                                                                                                                                                                   |
| AT06 Publishing  | BLOCKED | Atomic writes, locks, idempotent publication, retry status, correction, journal reconciliation, manifest ordering, and rollback paths are implemented and locally exercised. Forced process termination at every stage and low-disk fault injection are unavailable in this managed environment.  |
| AT07 Security    | PASS    | HTTPS/host/redirect restrictions, schema/path/output escaping, CLI-only maintenance, secret scan, dependency audits, and denial-oriented tests pass locally. Production host TLS and private-path checks await a chosen host.                                                                     |
| AT08 SEO         | PASS    | Browser SEO tests verify fixture canonicals, status behavior, metadata, sitemap exclusions, and bounded route patterns. Live production-origin verification awaits a domain.                                                                                                                      |
| AT09 Design      | BLOCKED | Manrope/Inter assets, responsive screenshots at 320/375/768/1024/1440, keyboard behavior, reduced-motion CSS, and automated axe checks pass. Safari/iOS, Android hardware, manual screen-reader, and 200% zoom checks require accessible devices/services.                                        |
| AT10 Performance | BLOCKED | Built CSS and JavaScript stay below the PRD's 30 KiB per-file budgets; the tested first-load local asset set is below 220 KiB, and solver filtering is local and bounded. Three-run Lighthouse medians and the permitted five-minute 50-reader hosted load test require a reachable preview/host. |
| AT11 Operations  | BLOCKED | Bounded retries, heartbeat/status records, logs, checksummed backup, verified restore to a new path, reconcile, and rollback inspection work locally. Independent monitoring, alert routing, and off-server backup storage require owner-selected services.                                       |
| AT12 Delivery    | BLOCKED | Lockfiles, setup commands, CI, docs, fixture generation, and local checks are present. Private Sites deployment and private GitHub remote/SHA verification require explicit upload authorization and GitHub authentication/repository access.                                                     |

## Requirement-family mapping

| Family                  | Status  | Primary files/evidence                                                                                                                    |
| ----------------------- | ------- | ----------------------------------------------------------------------------------------------------------------------------------------- |
| FR reader features      | PASS    | `templates/`, `assets/js/`, `SiteBuilder`, browser/Vitest tests                                                                           |
| DT data truth and dates | BLOCKED | Schemas, `SourcePayloadValidator`, `GlobalDateEligibility`; live regional source observation and licensed production data remain external |
| SL source layer         | BLOCKED | `NytSourceClient`, `PuzzleNumberResolver`, source unit tests; live source credentials/access and observation remain external              |
| ENG engineering         | PASS    | Composer/npm lockfiles, PHPStan level 8, formatting, lint, unit/browser checks, pinned CI actions                                         |
| SEC security            | PASS    | `docs/SECURITY.md`, secret scan, HTTPS allowlist, bounded responses, escaping, dependency audits                                          |
| PERF performance        | BLOCKED | Local asset measurements pass; hosted Lighthouse and load testing remain external                                                         |
| SEO discovery           | PASS    | Templates, sitemap/robots generation, canonical rules, SEO browser suite                                                                  |
| Operations              | BLOCKED | CLI runbooks and local recovery evidence pass; independent monitor and off-server backups remain external                                 |

## External inputs still required

- Explicit permission to upload generated preview source to the owner-only OpenAI Sites repository.
- Authenticated GitHub identity and a private repository destination.
- Pinned, licensed production vocabulary and definition datasets.
- Production origin/domain, contact email, host limits, scheduler, monitor destination, and off-server backup target.
- Accessible Safari/iOS, Android, and screen-reader test environments.
