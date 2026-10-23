# Backup, restore and rollback

## Backup

Run daily and weekly classes separately:

```powershell
php bin/wordle backup --config=config/site.local.json --mode=production --dry-run
php bin/wordle backup --config=config/site.local.json --mode=production
php bin/wordle backup --config=config/site.local.json --mode=production --weekly
```

Each backup stages the listed private records, revisions, definitions, vocabulary, journal directories, and the configured status file at its private-relative path when they exist. It always copies the non-secret JSON configuration, compares the staged copy with pre-copy source hashes, writes SHA-256 for every regular file, validates the application manifest, and then promotes the directory. Publisher logs and the lock file are intentionally excluded.

Verification rejects symbolic links and any missing, changed, or extra regular file, excluding only `backup-manifest.json` from the file map. The manifest must have the supported core fields and match the backup's full `daily-YYYYMMDDTHHMMSSZ` or `weekly-YYYYMMDDTHHMMSSZ` directory name. Automated retention counts and prunes only correctly named backups that pass that application verification; malformed or corrupt candidates remain in place for investigation. A cleanup failure is reported as a warning after the new durable backup is acknowledged, avoiding an ambiguous “backup failed” result.

### Integrity and authenticity boundary

The SHA-256 manifest detects corruption relative to the manifest supplied with the backup. Because that manifest is stored in the same directory and is not signed or authenticated, it does not prove provenance against an attacker who can replace both the files and the manifest.

Local backups are not sufficient. Before launch, copy backups to owner-controlled encrypted, immutable/versioned off-server storage, verify them again after transfer, and keep a separately protected digest/signature catalog or equivalent trust anchor. Keep encryption/authentication keys somewhere that does not depend on the failed host. Configure retention locking or equivalent deletion protection and test retrieval from the off-server copy.

## Restore drill

1. Stop the scheduler or ensure the publisher lock is free.
2. Verify without writing:

   ```powershell
   php bin/wordle restore daily-20261016T120000Z --target=C:\new\private-root --config=config/site.local.json --mode=production
   ```

3. Add `--apply`. The command refuses an existing, broad, active, or overlapping target; verifies the exact backup tree; copies only the manifest-listed private files into a new staging directory; verifies that staged tree; promotes it; and validates every restored record schema/hash.
4. Review the backed-up `config.json` separately. It is integrity-checked reference material but is not copied into the private restore target. In a copied configuration, update both `private_storage` and `monitoring.status_path` to the new private root; do not edit the live configuration in place.
5. Run `doctor`, asset build, `build`, `reconcile` and browser smoke tests against a staging public root.
6. Promote the reviewed configuration/release, restart scheduling and verify heartbeat/public pages.

The final local fixture drill verified 6 backup files and restored/schema-validated 3 records into a new private path. Production recovery from owner-controlled immutable off-server storage, RPO ≤24h, and RTO ≤4h remain unproven until the chosen host/storage and independent trust material are available.

## Public-output rollback

`build` keeps one `public_output.rollback` directory. Every new public tree contains an exact-file integrity manifest. `rollback` verifies that retained tree, and `rollback --apply` repeats verification under the shared lock before swapping directories and preserving the displaced build. This is a public artifact rollback only. It never overwrites newer authoritative records. Diagnose, reconcile and regenerate from private records afterward.
