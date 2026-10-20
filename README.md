# Wordle Hit Today

Private/local implementation of the Wordle-help site defined in [prd.md](prd.md). The publisher is a PHP CLI that validates private records and generates a static site. Browser requests never execute PHP or contact the puzzle source.

The checked-in build is intentionally fixture-only: its three answers and small solver list are synthetic, the canonical origin is `https://example.invalid`, and generated preview pages display a DEMO banner. It is not authorized or configured for public production use.

## Architecture

- `bin/wordle` — Symfony Console publisher and operational commands.
- `src/` — clock, source validation, storage, publishing, monitoring, solver and hint modules.
- `templates/` — escaped Twig templates for static HTML.
- `assets/` and `scripts/build.mjs` — small native modules, CSS, local fonts and hashed assets.
- `schemas/` — versioned JSON Schema contracts.
- `resources/` — original editorial copy and fixture vocabulary.
- `tests/` — PHPUnit, Vitest, Playwright, axe, responsive and SEO checks.
- `var/private/`, `var/site/`, `var/backups/`, `var/logs/` — ignored runtime output.

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
php bin/wordle reconcile --mode=fixture
```

`composer check` runs formatting, PHPStan level 8, PHPUnit and Composer audit. `npm run check` runs ESLint, Prettier, a staged-source secret scan, deterministic assets, Vitest, Chromium behavior/responsive screenshots, axe and SEO checks.

## Production is deliberately gated

Production configuration requires a real HTTPS origin, contact address, absolute public/private paths, and a validated date/number anchor. It also requires imported licensed WordNet and ESDB/SCOWL-derived data. The asset manifest records whether its solver vocabulary is `fixture` or `production`; the PHP build rejects a mode mismatch.

Read [docs/CONFIGURATION.md](docs/CONFIGURATION.md), [docs/SOURCE_DATA.md](docs/SOURCE_DATA.md), [docs/SEO_KEYWORD_MAP.md](docs/SEO_KEYWORD_MAP.md), [docs/OPERATIONS.md](docs/OPERATIONS.md), [docs/RECOVERY.md](docs/RECOVERY.md) and [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) before enabling production mode.

## Current delivery blockers

- GitHub CLI is installed but no GitHub account is authenticated and no private remote exists.
- Final domain, contact address, hosting limits/access, monitor destination and off-server encrypted backup target are not supplied.
- The pinned production vocabulary/definition distributions and their verified checksums are not supplied.
- The referenced 6 and 8 October 2026 NYT sample-response files are not present in the workspace.
- Public launch is explicitly out of scope until the owner authorizes it.

See [BUILD_REPORT.md](BUILD_REPORT.md) and [REQUIREMENTS.md](REQUIREMENTS.md) for evidence and blocked checks.
