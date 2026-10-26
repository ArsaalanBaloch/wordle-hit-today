# Source and licensed data policy

## Daily answer source

Only the configured URL pattern `https://www.nytimes.com/svc/wordle/v2/YYYY-MM-DD.json` is accepted. The client disables redirects, verifies TLS through Symfony HttpClient, requests JSON, caps the response, and validates:

- HTTP 200 and JSON content type;
- a bounded JSON object rather than HTML or deeply nested data;
- exact requested `print_date`;
- five ASCII letters, normalized to uppercase;
- non-negative integer `days_since_launch` matching the configured puzzle-number anchor;
- bounded integer/string upstream ID and optional bounded editor text.

The upstream ID is retained as provenance and is never substituted for the public puzzle number. Structural validation is reported as “Source checked”; it is not a claim that a person independently played the game.

`fetch-date`, `backfill` and production `tick` are the only commands that make this external request. Public pages never do. Source failures retain existing pages and write only sanitized categories to status/logs; conflicting data is quarantined.

## Vocabulary import

Acquire a pinned ESDB/SCOWL-derived word list and exact license from the authorized source. Record the release/version and calculate SHA-256 independently.

```powershell
php bin/wordle import-vocabulary `
  --config=config/site.local.json --mode=production `
  --input=C:\data\words.txt --license=C:\data\LICENSE `
  --sha256=<64-lowercase-hex> --source-version=<pinned-release>
```

That is a dry validation. Add `--apply` to write `private_storage/vocabulary/solver.json` and its license. The importer accepts a prefiltered newline/TSV list, keeps ASCII five-letter forms, preserves the reviewed input order as ranking metadata, keeps the earliest rank for duplicates, and refuses fewer than 1,000 candidates. The source preparation process remains responsible for applying ESDB/SCOWL variant/proper-name metadata accurately.

The reproducible preparation command used for production is:

```powershell
npm run prepare:production-data -- `
  --esdb-dir=C:\data\wordlist-diff `
  --wordnet-dict=C:\data\wordnet-3.0\dict `
  --output-dir=C:\data\prepared
```

It selects lowercase ASCII five-letter entries from the pinned ESDB `en_US` size-60 dictionary first, followed by size-70 additions, which filters marked proper names and abbreviations while preserving the reviewed common/large tiers. It also removes WordNet usage examples, joins sense-order metadata, and writes a checksum manifest for every prepared output.

Build production assets with the imported file:

```powershell
$env:WORDLE_VOCABULARY_PATH='C:\absolute\private\vocabulary\solver.json'
npm run build
```

The generated asset manifest is marked `production`; a production PHP build rejects fixture assets.

## Definition import

Acquire the pinned Princeton WordNet 3.0 distribution and retain its license, copyright and disclaimer. Convert locally to a JSON array containing `lemma`, `part_of_speech`, `sense_id` and `gloss`. Usage examples should be removed by the converter before import.

```powershell
php bin/wordle import-definitions `
  --config=config/site.local.json --mode=production `
  --input=C:\data\wordnet-five-letter.json --license=C:\data\LICENSE `
  --sha256=<64-lowercase-hex> --source-version='Princeton WordNet 3.0'
```

Add `--apply` after reviewing counts. The importer omits malformed entries and glosses containing the answer or obvious suffix forms. Exact-form lookup is used; missing safe meanings do not block factual hints.

The deployment operator must retain the generated checksum manifest and the exact license texts with the private imported data. Public attribution links are generated from `resources/licenses/`.
