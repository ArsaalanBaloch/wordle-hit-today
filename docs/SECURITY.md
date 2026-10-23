# Security notes

- Maintenance is CLI-only. There is no public fetch/build/correction route.
- Source requests use one HTTPS client with a fixed allowlisted host/path, no redirects, normal certificate verification, bounded time/size and strict response validation.
- Record dates are strict real ISO dates before becoming partitioned paths. Journal/backup relative paths reject absolute, backslash and traversal forms.
- Managed roots and destructive targets are constrained by shared path policy. Atomic writes reject symbolic-link destinations.
- Assets are validated from the staged snapshot: exact manifest shape, safe allowlisted paths, exact file set, and content-addressed hashes must agree before promotion.
- Publication writes a durable staged journal before promotion and a published or failed state afterward. Record-set hashing makes an interrupted save/publication retryable.
- Publication reconciliation, self-contained rollback verification, and backup verification enumerate exact regular-file trees. They reject symbolic links and report missing, changed, or extra files instead of validating only manifest entries that happen to be present.
- Backup staging is compared with hashes captured from the locked source files. Manifests require the supported schema version, UTC creation time, daily/weekly class, matching full timestamp directory name, configuration entry, and application-private paths. Restore copies only that verified private-file set into a new staged target and verifies it again before promotion.
- Twig auto-escapes HTML; browser search results use `textContent`/DOM nodes rather than `innerHTML`.
- The preview router returns CSP, `nosniff`, Referrer-Policy and Permissions-Policy. Production HSTS is deferred until the domain and TLS are verified.
- `.gitignore` excludes credentials, runtime records, output, backups, logs, tool caches and test reports. `npm run scan:secrets` checks tracked plus untracked non-ignored readable files for common high-risk token/key forms.
- CI has read-only repository contents permission and no production secrets. Third-party actions are pinned to immutable commits.

## Integrity is not authenticity

The journal and backup SHA-256 values detect accidental corruption, incomplete copies, and unexpected files relative to the manifest being checked. The backup manifest is stored beside the backup and is not signed or authenticated. An attacker who can replace both the files and that manifest can create a self-consistent malicious backup; a successful checksum verification does not establish hostile-manifest authenticity or provenance.

Production recovery therefore also requires restricted write access and a separately protected trust anchor, such as an authenticated catalog/signature or a verified digest kept in owner-controlled immutable/versioned storage. Any signing or MAC design must include independent key custody; none is claimed by the current local manifest format.

## Production controls still pending

- Verify the final domain, DNS/TLS, production headers, document-root isolation, host filesystem behavior, scheduler, and cache controls.
- Import the pinned licensed production vocabulary and definition distributions and verify their source checksums and coverage.
- Configure independent monitoring and encrypted immutable off-server backups, then perform a full restore drill.
- Complete physical Safari/iOS, Android, screen-reader/zoom, and hosted performance/load checks.

Dependency audits are part of `composer check` and `npm run check`/lockfile review. Repository privacy and the secret scanner are defense-in-depth, not guarantees that every secret format or unauthorized disclosure is prevented. Review `git diff --cached` before every push and never commit production credentials.
