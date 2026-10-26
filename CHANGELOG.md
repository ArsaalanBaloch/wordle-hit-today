# Changelog

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
