# Deployment adapter contract

No public deployment is authorized in this delivery. Select the host only after its PHP CLI version, filesystem semantics, cron interval, document-root controls, atomic rename behavior, disk/inode limits, cache headers and backup/monitor APIs are verified.

A host adapter must:

1. acquire the same stable publisher lock outside release directories;
2. deploy code/dependencies without touching configured private runtime storage;
3. verify configuration/schema compatibility;
4. build in a staged public path;
5. validate hashes, internal links, metadata, status codes and required licenses;
6. promote by an atomic release pointer or tested same-filesystem rename;
7. verify the canonical HTTPS URL and cache/security headers;
8. retain a compatible prior release and never roll runtime records back with code;
9. avoid destructive synchronization against shared storage.

Use least-privilege SSH/SFTP or the host’s reviewed Git deployment, with host identity verification. Do not put credentials in JSON, Git, CI logs or command arguments. Production daily publication is performed by `tick` from hosting cron and does not create Git commits.

Before launch, supply and test: real domain/contact, DNS/TLS, host access/limits, scheduler, independent monitoring destination, encrypted off-server backup, cache expiry/purge behavior, 404/503 handling, Search Console ownership and a rollback drill. HSTS is enabled only after HTTPS/domain verification.
