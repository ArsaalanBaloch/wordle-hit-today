# Wireframes and state inventory

These compact wireframes guided the implemented responsive structure. Actual inspected screenshots are in `docs/screenshots/` at 320, 375, 768, 1024 and 1440 CSS pixels.

## Home

```text
[DEMO / status strip]
[brand] [Today Solver Past Answers How to Play]
[local date status]
[title + intro]
[pending / manifest / Intl state]
[date picker]
[vowel disclosure]
[repeat disclosure]
[optional meaning]
[first/final disclosures]
[five answer tiles] [show all] [share]
[recent spoiler-free cards]
[solver call-to-action]
[short methodology]
[footer]
```

## Solver

```text
[header]
[instructions]
[6 × (5 letter fields + feedback cycle + direct select)] [reset/retry]
[candidate count + first 20 + load more]
[loading / failure / unknown word / zero-result state]
[coverage and duplicate-letter explanation]
[footer]
```

At narrow widths, candidate results move below the grid and five cells remain within 320px. Navigation uses a native disclosure menu, so links work without a JavaScript overlay.

## Archive and dated page

```text
Archive: [title] [exact search + status] [year links] [dated cards] [pagination]
Dated:  [fixed date/number] [local-today link] [reveals] [correction] [prev/archive/next]
```

States covered in source/tests: pending local date, missing definition, no archive result, invalid query, contradictory solver feedback, solver asset failure/retry, manifest failure/fallback, no JavaScript and long mobile content. Manual screen-reader, 200% zoom, Firefox/Safari and physical iOS/Android checks remain blocked by available hardware/software.
