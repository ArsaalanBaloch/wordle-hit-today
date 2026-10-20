# Publisher operations

All mutating publisher commands share `private_storage/publisher.lock`. Lock contention is a successful safe skip. Exit codes are `0` success/safe skip, `10` invalid input/configuration, `20` blocked source/network, and `30` storage/build failure.

| Command | External request | Write behavior and safety |
| --- | --- | --- |
| `doctor` | No | Read-only environment/config checks. |
| `build-fixtures` | No | Fixture-only, deterministic, idempotently replaces same private records. |
| `import-vocabulary` | No | Dry by default; `--apply` writes normalized data/license after checksum validation. |
| `import-definitions` | No | Dry by default; `--apply` writes safe exact-form entries/license. |
| `fetch-date DATE` | One HTTPS GET | Production only; `--dry-run` validates without saving; refuses an existing record. |
| `build` | No | Stages, validates, checksums, retains previous output and promotes. |
| `tick` | Bounded eligible-date GETs when due | Heartbeat every successful lock acquisition; retry state prevents per-minute fetching; one publication after changes. Fixture mode never fetches. |
| `backfill` | Up to 31 GETs | `--dry-run` lists; `--confirm` authorizes missing eligible dates and one final build. |
| `correct DATE` | No | Dry diff by default; requires expected revision, local evidence, public notice, `--confirm-evidence --apply`; retains prior revision. |
| `reconcile` | No | Verifies every journal hash; `--repair` rebuilds from authoritative records. |
| `status` | No | Read-only sanitized state; `--json` for monitoring adapters. |
| `backup` | No | `--dry-run` available; copies selected private data/config to a staged checksummed daily/weekly directory and applies retention. |
| `restore BACKUP` | No | Verifies by default; `--apply --target=NEW_PATH` restores only to a non-existing path. Never overwrites live data. |
| `rollback` | No | Inspect by default; `--apply` promotes retained public output and keeps displaced output. Private records are untouched. |
| `preview` | No | Loopback-only router with real 301/404 and security/cache headers. |

## Scheduler

Run `tick` once per minute if the host permits. Pending dates are requested no more frequently than 5, 15, 30 and 60 minutes after successive transient failures, then hourly. `Retry-After` is respected with the configured minimum. Denial backs off for a day; structurally invalid/conflicting responses are quarantined. Successful records are rechecked at roughly 1h, 6h and the following day, then marked complete.

Example Windows Task Scheduler action:

```text
Program: C:\path\to\php.exe
Arguments: C:\path\to\repo\bin\wordle tick --config=C:\secure\site.json --mode=production
Start in: C:\path\to\repo
```

If the host supports only five-minute cron, the same command is safe, but source freshness and recovery checks can lag by that interval.

## Monitoring

`status --json` exposes heartbeat, last source success/publication and per-date retry categories without answers, credentials or raw upstream bodies. Before launch, connect an independent monitor outside the hosting scheduler. Initial thresholds are 15 minutes without heartbeat and 30 minutes for an eligible pending date. Test one alert, bounded reminders and recovery notification with the actual destination.

Operational NDJSON logs are limited to 5 MiB per UTC day and retained 30 days. Entries contain run/date/stage/result/retry/duration/publication time only.
