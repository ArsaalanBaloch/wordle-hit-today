# Wordle Hit Today

Private implementation of the Wordle-help site defined in [prd.md](prd.md). The publisher is a PHP CLI that validates private records and generates a static site. Browser requests never execute PHP or contact the puzzle source.

The checked-in build is intentionally fixture-only: its three answers and small solver list are synthetic, the canonical origin is `https://example.invalid`, and generated preview pages display a DEMO banner. It is not authorized or configured for public production use.

## Delivery status

The configured GitHub `origin` is the verified private delivery target. The audited source, generated assets, local quality evidence, and GitHub Actions workflow pass are complete; the exact final commit and matching remote SHA are reported in the delivery handoff.

The production site is live at `https://wordlehintoday.com/` with apex-only DNS, TLS, HTTPS redirection, the validated production dataset, and automatic publication plus daily/weekly on-host backup schedules. The public publisher normalizes web-readable directory and file permissions on every atomic build. Independent monitoring, live device/hosted performance evidence, a working branded mailbox, and encrypted immutable off-server backups remain.

## Architecture

- `bin/wordle` — Symfony Console publisher and operational commands.
- `src/` — clock, source validation, storage, publishing, monitoring, solver and hint modules.
- `templates/` — escaped Twig templates for static HTML.
- `assets/` and `scripts/build.mjs` — small native modules, CSS, local fonts and hashed assets.
- `schemas/` — versioned JSON Schema contracts.
- `resources/` — original editorial copy and fixture vocabulary.
- `tests/` — PHPUnit, Vitest, Playwright, axe, responsive and SEO checks.
- `var/private/` (including operational logs), `var/site/`, and `var/backups/` — ignored runtime output.

## Requirements

- PHP 8.4+ with cURL, JSON and OpenSSL.
- Composer 2.
- Node.js 24 LTS and npm for asset/test tooling only.
- Git.

Tested locally on Windows with PHP 8.5.11, Composer 2.10.3, Node 24.21.0, npm 11.19.0 and Git 2.56.0.windows.1. The repository-local `.tools/` directory is ignored and is not part of a fresh checkout.

## Fixture quick start

```powershell
composer install
npm ci
php bin/wordle doctor --mode=fixture
php bin/wordle build-fixtures --mode=fixture
npm run build
php bin/wordle build --mode=fixture
php bin/wordle preview --host=127.0.0.1 --port=8080 --mode=fixture
```

Then open `http://127.0.0.1:8080/`. Stop the preview with Ctrl+C. If a managed environment forbids listening sockets, run the checks below; Playwright uses a read-only in-process static route and does not require a server port.

## Validation

```powershell
composer check
npm run check
npm run audit:seo
php bin/wordle reconcile --mode=fixture
```

`composer check` runs formatting, PHPStan level 8, PHPUnit and Composer audit. `npm run check` runs ESLint, Prettier, a tracked/untracked non-ignored file secret scan, deterministic assets, Vitest, Chromium behavior/responsive screenshots, axe and SEO checks. `npm run audit:seo` can also audit an already generated `var/site` tree directly.

Publication stages and hash-validates its asset snapshot, writes a durable pre-promotion journal, and gives each public build a self-contained exact-tree integrity manifest. Reconciliation, rollback, backup, and restore reject missing, changed, injected, unsafe, or symbolic-link entries. These checks detect corruption relative to the supplied manifest; they do not authenticate a manifest against an attacker who can replace both content and manifest. See [docs/SECURITY.md](docs/SECURITY.md) and [docs/RECOVERY.md](docs/RECOVERY.md).

## Production is deliberately gated

Production configuration enforces a real HTTPS origin, contact address, absolute public/private paths, and a validated date/number anchor. The asset manifest records whether its solver vocabulary is `fixture` or `production`, and the PHP build rejects a mode mismatch. `npm run prepare:production-data` reproducibly prepares the pinned ESDB/SCOWL vocabulary and Princeton WordNet definitions, records source/output checksums, and reports coverage. Missing or unsafe definitions are deliberately omitted rather than fabricated.

Read [docs/CONFIGURATION.md](docs/CONFIGURATION.md), [docs/SOURCE_DATA.md](docs/SOURCE_DATA.md), [docs/SEO_KEYWORD_MAP.md](docs/SEO_KEYWORD_MAP.md), [docs/SEO_AUDIT.md](docs/SEO_AUDIT.md), [docs/SEO_IMPLEMENTATION_REPORT.md](docs/SEO_IMPLEMENTATION_REPORT.md), [docs/OPERATIONS.md](docs/OPERATIONS.md), [docs/SECURITY.md](docs/SECURITY.md), [docs/RECOVERY.md](docs/RECOVERY.md), [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) and [docs/HOSTINGER.md](docs/HOSTINGER.md) before enabling production mode.

## Remaining work

- Observe the automatic publication and both backup schedules through their next due runs; configure an independent stale-heartbeat monitor.
- Activate the planned `contact@wordlehintoday.com` mailbox. The address is displayed on the site but is not monitored yet.
- Complete Safari/iOS, Android, manual screen-reader/zoom, hosted Lighthouse, and permitted load testing.
- Configure an owner-controlled encrypted, immutable/versioned off-server backup and perform a full restore drill.

See [BUILD_REPORT.md](BUILD_REPORT.md) and [REQUIREMENTS.md](REQUIREMENTS.md) for evidence and blocked checks.
