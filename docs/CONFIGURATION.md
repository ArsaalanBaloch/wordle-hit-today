# Configuration

Copy `config/site.example.json` to an ignored path such as `config/site.local.json`. JSON contains no secrets. CLI flags only select a configuration and assert its mode; they cannot silently convert fixture data into production data.

## Important fields

| Field | Effect |
| --- | --- |
| `mode` | `fixture` or `production`; records and built solver assets must match. |
| `canonical_origin` | Absolute HTTPS origin used in canonicals, sitemap and structured data. Production rejects `.invalid`. |
| `puzzle_number_anchor` | Production-only `{date, number}` verified against known public puzzle numbering. Source `days_since_launch` must agree. |
| `public_output` | Static document root. Production requires an absolute path. |
| `private_storage` | Records, definitions, vocabulary, journal, state and logs. Production requires an absolute non-public path. |
| `source_poll_minutes` | Minimum source interval; schema minimum is five minutes. |
| `source` | Fixed NYT host/path plus 10s connection, 20s total and 64 KiB response ceilings. Host/path are schema-allowlisted. |
| `monitoring.status_path` | Sanitized heartbeat/retry status; keep outside the web root unless a deliberate sanitized status adapter is added. |
| `backup.local_path` | Local backup root, separate from private runtime storage. |
| `contact_address` | Required email in production. |

Production paths should point to persistent storage outside a release directory. The public document root must expose only `public_output`; it must not expose this repository, `vendor/`, `resources/`, private storage, logs or backups.

## Production validation example

Use real values; this fragment is intentionally incomplete and must not be copied as-is:

```json
{
  "mode": "production",
  "canonical_origin": "https://your-real-domain.example",
  "puzzle_number_anchor": {"date": "YYYY-MM-DD", "number": 0},
  "public_output": "C:/absolute/public/path",
  "private_storage": "C:/absolute/private/path",
  "contact_address": "real-address@your-domain.example"
}
```

Run `php bin/wordle doctor --config=... --mode=production` after every configuration change. A domain change requires a full rebuild because canonical URLs, sitemap and structured data change.
