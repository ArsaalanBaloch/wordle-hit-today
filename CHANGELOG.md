# Changelog

## 0.1.4 — 2026-10-26

- Launched the verified production build on the apex `wordlehintoday.com` domain with TLS, HTTPS redirection, live robots/sitemap metadata, and the automatic publication and backup schedules enabled.
- Normalized generated public directories to `0755` and files to `0644` before atomic promotion so Hostinger/LiteSpeed can serve every scheduled build reliably.
- Added canonical-host redirect protection, moved the Hostinger primary website and deployment root to `wordlehintoday.com`, and removed the former free-domain website after verification.
- Created a verified migration backup and documented the still-pending branded mailbox, independent monitoring, device testing, and off-server backup work.

## 0.1.3 — 2026-10-26

- Added reproducible production-data preparation from pinned ESDB/SCOWL and Princeton WordNet releases, including source manifests, checksums, license notices and coverage tests.
- Preserved source frequency order during vocabulary import, improved production content disclosures and aligned privacy/contact wording with the static Hostinger deployment.
- Prepared and verified the Hostinger production runtime, private data, first live-source publication, reconciliation and backup without replacing the public noindex fixture.

## 0.1.2 — 2026-10-26

- Added a generated Hostinger web-server adapter with custom 404 handling, directory protection, security headers and immutable caching for content-addressed assets.
- Added two-layer `noindex, nofollow` protection for fixture staging and made the SEO auditor validate fixture and production indexing contracts separately.
- Recorded and documented the verified noindex Hostinger staging target and atomic rollback-safe publication process.

## 0.1.1 — 2026-10-23

- Hardened managed-path overlap, restore, rollback, asset-snapshot, and exact-tree integrity checks.
- Added durable staged/published/failed publication journals and automatic retry detection for records saved before an interrupted publication.
- Made backup copies source-hash verified, preserved configured nested status paths, and reported retention cleanup separately from successful backup creation.
- Reduced and indexed browser work, removed an unused dependency/font weight, tightened preview routing, and added security/recovery regression coverage.

## 0.1.0 — 2026-10-16

- Added the fixture/static publisher, progressive hints, controlled answer tiles, device-local date handling, archive/search, solver and original guide pages.
- Added strict configuration/record/journal/manifest/status schemas, atomic records, staging/promotion and locking.
- Added bounded NYT source validation, anchored public puzzle numbers, retry/recheck scheduler, correction revisions and reconciliation.
- Added checksum-gated vocabulary/definition import, definition safety filtering and fixture/production asset-mode enforcement.
- Added checksummed backup/restore, artifact rollback and sanitized operational state/logging.
- Added PHPUnit, PHPStan, PHP CS Fixer, Vitest, Playwright, axe, responsive/SEO checks, secret scanning and pinned CI actions.
