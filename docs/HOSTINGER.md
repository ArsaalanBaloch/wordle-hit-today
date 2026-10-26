# Hostinger deployment

The Hostinger target is recorded in `.hostinger/site.json`. The public website is a generated static tree; application source, dependencies, runtime records, logs and backups must remain outside `public_html`.

## Verified production environment

- Primary domain: `https://wordlehintoday.com`
- Hosting home: `/home/u164418876`
- Document root: `/home/u164418876/domains/wordlehintoday.com/public_html`
- Application release link: `/home/u164418876/apps/wordle-hit-today/current`
- Private records: `/home/u164418876/private/wordle-hit-today`
- Backups: `/home/u164418876/backups/wordle-hit-today`
- PHP 8.4 CLI: `/opt/alt/php84/usr/bin/php`
- Composer: available on the hosting account

The former `aqua-turkey-495051.hostingersite.com` website was removed after the production site, SSL, scheduler and backups were verified on the custom domain. It is not a deployment or preview target.

## Publication rules

1. Build and validate the complete static tree before upload.
2. Upload to a new sibling staging directory, never directly into `public_html`.
3. Verify `index.html`, `.htaccess`, `build-integrity.json` and the expected file count.
4. Preserve the current `public_html` as a rollback directory before an atomic same-filesystem rename.
5. Verify real page content, status codes, redirects, cache policy and security headers over HTTPS.

Production indexing, cron publication and on-host backups are enabled. The branded mailbox, independent monitoring and encrypted off-server backup remain separate operational tasks.
