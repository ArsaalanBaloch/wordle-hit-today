# Security notes

- Maintenance is CLI-only. There is no public fetch/build/correction route.
- Source requests use one HTTPS client with a fixed allowlisted host/path, no redirects, normal certificate verification, bounded time/size and strict response validation.
- Record dates are strict real ISO dates before becoming partitioned paths. Journal/backup relative paths reject absolute, backslash and traversal forms.
- Atomic writes reject symbolic-link destinations. Staged output and publisher locking reduce partial publication risk.
- Twig auto-escapes HTML; browser search results use `textContent`/DOM nodes rather than `innerHTML`.
- The preview router returns CSP, `nosniff`, Referrer-Policy and Permissions-Policy. Production HSTS is deferred until the domain and TLS are verified.
- `.gitignore` excludes credentials, runtime records, output, backups, logs, tool caches and test reports. `npm run scan:secrets` checks staged/untracked non-ignored text for common high-risk token/key forms.
- CI has read-only repository contents permission and no production secrets. Third-party actions are pinned to immutable commits.

Dependency audits are part of `composer check` and `npm run check`/lockfile review. The scanner is defense-in-depth, not a guarantee that every secret format is detectable. Review `git diff --cached` before every push.
