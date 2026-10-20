# Schema versions

All current documents are schema version 1. There is no prior persisted version to migrate. A future schema change must add an explicit migration that either preserves old records/configuration or stops with recovery instructions; silently accepting a changed version is not allowed.

- `config.schema.json` — public/default configuration contract.
- `record.schema.json` — private validated puzzle record and optional correction notice.
- `journal.schema.json` — publication file/hash journal.
- `published-dates.schema.json` — spoiler-free public date/index manifest.
- `status.schema.json` — sanitized heartbeat/retry state.
