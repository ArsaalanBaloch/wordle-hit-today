# Backup, restore and rollback

## Backup

Run daily and weekly classes separately:

```powershell
php bin/wordle backup --config=config/site.local.json --mode=production --dry-run
php bin/wordle backup --config=config/site.local.json --mode=production
php bin/wordle backup --config=config/site.local.json --mode=production --weekly
```

Each backup stages records, revisions, definitions, vocabulary, journal, status and the non-secret JSON configuration; writes SHA-256 for every file; promotes the directory; then keeps the configured seven daily/four weekly copies. Publisher logs and the lock file are intentionally excluded.

Local backups are not sufficient. Before launch, copy at least one verified backup to owner-controlled encrypted off-server storage. Keep the encryption key somewhere that does not depend on the failed host.

## Restore drill

1. Stop the scheduler or ensure the publisher lock is free.
2. Verify without writing:

   ```powershell
   php bin/wordle restore daily-YYYYMMDDTHHMMSSZ --target=C:\new\private-root --config=... --mode=production
   ```

3. Add `--apply`. The command refuses an existing/broad/active target, verifies every checksum, restores into a staging directory, promotes it and validates every record schema/hash.
4. Point a copied reviewed configuration at the new private path; do not edit the live configuration in place.
5. Run `doctor`, asset build, `build`, `reconcile` and browser smoke tests against a staging public root.
6. Promote the reviewed configuration/release, restart scheduling and verify heartbeat/public pages.

The local fixture drill restored three records successfully. Production off-server restore, RPO ≤24h and RTO ≤4h remain unproven until the chosen host/storage are available.

## Public-output rollback

`build` keeps one `public_output.rollback` directory. `rollback` inspects it; `rollback --apply` swaps it under the shared lock and preserves the displaced build. This is a public artifact rollback only. It never overwrites newer authoritative records. Diagnose, reconcile and regenerate from private records afterward.
