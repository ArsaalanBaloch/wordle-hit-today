# Direct dependency inventory

Resolved versions are authoritative in `composer.lock` and `package-lock.json`.

| Dependency | Purpose | Resolved version/license in this build |
| --- | --- | --- |
| opis/json-schema | JSON Schema validation | 2.6.0, Apache-2.0 |
| symfony/console/filesystem/http-client | CLI, safe filesystem operations and bounded HTTPS | 8.1.x, MIT |
| twig/twig | Escaped static templates | 3.30.0, BSD-3-Clause |
| PHPUnit | PHP unit/integration tests | 12.5.40, BSD-3-Clause |
| PHPStan | Static analysis | 2.3.1, MIT |
| PHP CS Fixer | PSR-12/style checks | 3.95.27, MIT |
| esbuild | Small minified browser bundles | 0.25.12, MIT |
| Vitest | JavaScript unit tests | 4.1.12, MIT |
| Playwright | Browser/responsive/SEO tests | 1.64.1, Apache-2.0 |
| axe-core Playwright | Automated accessibility checks | 4.14.0, MPL-2.0 |
| ESLint / Prettier | JavaScript/CSS/JSON quality | 10.12.0 / 3.9.10, MIT |
| Fontsource Inter/Manrope | Local Latin WOFF2 build inputs | 5.3.0, OFL-1.1 |

Publisher serialization uses PHP's native `flock`; no separate locking package is required. The public asset build copies the actual Inter and Manrope OFL license texts. Production WordNet and ESDB/SCOWL-derived datasets are not checked in or claimed imported; their exact licenses/checksums must accompany the import.
