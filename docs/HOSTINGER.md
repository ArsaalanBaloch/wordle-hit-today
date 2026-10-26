# Hostinger deployment

The Hostinger target is recorded in `.hostinger/site.json`. The public website is a generated static tree; application source, dependencies, runtime records, logs and backups must remain outside `public_html`.

## Verified staging environment

- Temporary domain: `https://aqua-turkey-495051.hostingersite.com`
- Hosting home: `/home/u164418876`
- Document root: `/home/u164418876/domains/aqua-turkey-495051.hostingersite.com/public_html`
- PHP 8.4 CLI: `/opt/alt/php84/usr/bin/php`
- Composer: available on the hosting account

The temporary domain is fixture-only. Fixture builds emit both a `noindex, nofollow` HTML directive and an `X-Robots-Tag: noindex, nofollow` response header because Hostinger may override `robots.txt` on free subdomains.

## Publication rules

1. Build and validate the complete static tree before upload.
2. Upload to a new sibling staging directory, never directly into `public_html`.
3. Verify `index.html`, `.htaccess`, `build-integrity.json` and the expected file count.
4. Preserve the current `public_html` as a rollback directory before an atomic same-filesystem rename.
5. Verify real page content, status codes, redirects, cache policy and security headers over HTTPS.

Do not connect the production domain or enable indexing until production records, licensed solver vocabulary, definitions, the branded contact mailbox, cron, monitoring and backup/restore checks are complete.
