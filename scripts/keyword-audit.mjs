import { readFile, writeFile } from "node:fs/promises";
import path from "node:path";
import process from "node:process";
import { pathToFileURL } from "node:url";
import { parseArgs } from "node:util";

const PAGE_GROUPS = {
  daily: {
    intent: "Current daily hints and answer",
    target: "/",
    placement: "Homepage daily-help content and FAQ",
  },
  solver: {
    intent: "Interactive candidate filtering",
    target: "/wordle-solver/",
    placement: "Solver instructions, controls, vocabulary notes and FAQ",
  },
  archive: {
    intent: "Historical answer lookup",
    target: "/wordle-answers/",
    placement: "Archive search, historical navigation and FAQ",
  },
  rules: {
    intent: "Learn Wordle rules",
    target: "/how-to-play-wordle/",
    placement: "Rules guide and FAQ",
  },
  strategy: {
    intent: "Improve Wordle play",
    target: "/wordle-strategy/",
    placement: "Strategy guide, starting-word advice and tips",
  },
  broad: {
    intent: "General Wordle help",
    target: "/",
    placement: "Homepage overview and internal navigation",
  },
};

const COMPETITOR_PATTERN =
  /\b(?:mashable|tom s|toms?|tomsguide|techradar|forbes|newsweek|cnet|rock paper shotgun|try hard(?:er)?(?: guides?)?|tryhard(?: guides?)?|tryhardguides|hard guides?|capitalize my title|word ?tips|reddit)\b/;
const OFFICIAL_NAVIGATION_PATTERN =
  /\b(?:nyt|nytimes|ny times|new york(?: time| times| wordle)?|newyork times|newyorktimes|n y times|ny ?time|powerlanguage|power language|ny wordle|nyc wordle|wordle ny|wordle nyc|wordle new york|times wordle|wordle times|google wordle|wordle google)\b/;
const OTHER_GAME_PATTERN =
  /\b(?:unlimited|unlimted|unlimeted|unlimtied|unlimitied|unlimated|unlimite|unlimites|ulimited|umlimited|unlim|inlimited|unimited|unlmited|infinite|infinte|infinate|endless|forever|custom|costom|costum|make (?:a|your own)?|maker|creator|generator|variants?|variations?|alternatives?|games? like|similar to|other wordle games?|different (?:types of )?wordle|wordle type games?|connections?|waffle|quordle|quad wordle|heardle|hurdle|anti wordle|antiwordle|octordle|octo wordle|dordle|sedecordle|worldle|globle|spelling bee|strands|solitaire|crossword|cross wordle|sudoku|country|countries|geography|map wordle|wordle map|math|movie|music|song|sports?|soccer|football|nfl|nba|mlb|hockey|basketball|pokemon|disney|marvel|naruto|star wars|taylor swift|one piece|flag|clash royale|clash wordle|word master|hello wordle|wordle together|with friends|multiplayer|1v1|reverse wordle|double wordle|triple wordle|multiple wordle|mini wordle|wordle mini|wordle jr|wordle junior|kids?|animal wordle|wordle blossom|foodle|cryptic wordle|number wordle|phone number wordle|long wordle)\b|\b(?:2|3|4|6|7|8|10|11|16|32|64)[ -]?(?:letter|letters|word)? ?wordle\b|\bwordle (?:2|4|6|16|32|64)(?: letters?)?\b|\b(?:four|six|eight|eleven) letter wordle\b/;
const NON_ENGLISH_PATTERN =
  /\b(?:spanish|espanol|espan ol|deutsch|german|french|francais|franc ais|italian|italiano|latin|le mot|wordle es)\b/;
const PLAY_INTENT_PATTERN =
  /\b(?:play|gameplay|online|free|games?|website|site|link|download|mobile app|wordle app|unblocked|login|\. com)\b/;
const UNSUPPORTED_FEATURE_PATTERN =
  /\b(?:bot|replay|practice|review|analyzer|create|multiplayer)\b/;
const SPECIFIC_PUZZLE_PATTERN =
  /\b(?:jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)\s+\d{1,2}\b|\bwordle\s+#?\d{3,5}\b/;
const PREDICTIVE_PATTERN =
  /\b(?:not used|unused|not been used|not used yet|future answer|words left|remaining answers)\b/;

export function parseCsv(source) {
  const rows = [];
  let row = [];
  let field = "";
  let quoted = false;

  for (let index = 0; index < source.length; index += 1) {
    const character = source[index];
    if (quoted) {
      if (character === '"' && source[index + 1] === '"') {
        field += '"';
        index += 1;
      } else if (character === '"') {
        quoted = false;
      } else {
        field += character;
      }
    } else if (character === '"') {
      quoted = true;
    } else if (character === ",") {
      row.push(field);
      field = "";
    } else if (character === "\n") {
      row.push(field.replace(/\r$/, ""));
      rows.push(row);
      row = [];
      field = "";
    } else {
      field += character;
    }
  }
  if (field !== "" || row.length > 0) {
    row.push(field.replace(/\r$/, ""));
    rows.push(row);
  }
  if (quoted) throw new Error("Keyword CSV contains an unclosed quoted field.");

  const [rawHeaders, ...values] = rows.filter((entry) => entry.some(Boolean));
  if (!rawHeaders) throw new Error("Keyword CSV is empty.");
  const headers = rawHeaders.map((header, index) =>
    (index === 0 ? header.replace(/^\uFEFF/, "") : header).trim(),
  );
  return values.map((entry, rowIndex) => {
    if (entry.length !== headers.length) {
      throw new Error(`Keyword CSV row ${rowIndex + 2} has the wrong field count.`);
    }
    return Object.fromEntries(headers.map((header, index) => [header, entry[index]]));
  });
}

export function normalizePhrase(value) {
  return value
    .normalize("NFKD")
    .toLowerCase()
    .replace(/[’']/g, " ")
    .replace(/[^a-z0-9]+/g, " ")
    .trim()
    .replace(/\s+/g, " ");
}

function result(group, exclusionReason = "") {
  if (!group) {
    return {
      group: "Excluded",
      intent: "Unsupported or misleading intent",
      target: "—",
      placement: "—",
      exclusionReason,
      relevant: false,
    };
  }
  return { ...PAGE_GROUPS[group], group, exclusionReason: "", relevant: true };
}

export function classifyKeyword(originalKeyword) {
  const keyword = normalizePhrase(originalKeyword);
  if (!keyword.includes("wordle")) {
    return result(null, "Not sufficiently relevant to Wordle");
  }
  if (COMPETITOR_PATTERN.test(keyword)) {
    return result(null, "Competitor-specific navigational intent");
  }
  if (OFFICIAL_NAVIGATION_PATTERN.test(keyword)) {
    return result(
      null,
      "Official-site navigational intent; excluded to avoid implying NYT affiliation",
    );
  }
  if (NON_ENGLISH_PATTERN.test(keyword)) {
    return result(null, "Non-English intent is outside the current English site scope");
  }
  if (OTHER_GAME_PATTERN.test(keyword)) {
    return result(null, "Different game, variant or unsupported playable feature");
  }
  if (SPECIFIC_PUZZLE_PATTERN.test(keyword)) {
    return result(null, "Specific puzzle/date is not present in the validated archive");
  }
  if (PREDICTIVE_PATTERN.test(keyword)) {
    return result(null, "Predictive or unused-answer intent is not supported");
  }
  if (
    /\b(?:how to play|rules?|how does wordle|how do you|what is wordle|colou?rs?|green tile|yellow tile|gr[ae]y tile|same letter|repeated letters?|six guesses)\b/.test(
      keyword,
    )
  ) {
    return result("rules");
  }
  if (PLAY_INTENT_PATTERN.test(keyword)) {
    return result(null, "Playable-game intent; this site provides hints, guides and tools only");
  }
  if (UNSUPPORTED_FEATURE_PATTERN.test(keyword)) {
    return result(null, "Feature is not provided by the current website");
  }

  if (
    /\b(?:yesterday|yesterdays|previous|prior|past|archives?|history|already used|used wordle|wordle (?:words )?used|used words|words used in wordle|wordle (?:word )?list|list of wordle|list of used|answer list|what was|ever repeated)\b/.test(
      keyword,
    )
  ) {
    return result("archive");
  }
  if (
    /\b(?:solver|sovler|solve|helper|assist|finder|wordfinder|find|guesser|guess|calculator|calcle|calc|cheats?|cheater|cheating|dictionary|word search|unscrambl\w*|scrambl\w*|5 letter|five letter|wordle tool|help tool)\b/.test(
      keyword,
    )
  ) {
    return result("solver");
  }
  if (
    /\b(?:starting|starters?|start words?|words to start|first word|first guess|second guess|guesses|strategy|tips?|good word|best word|best words|hard mode|hardest word|hard word)\b/.test(
      keyword,
    )
  ) {
    return result("strategy");
  }
  if (
    /\b(?:hint|hints|himt|himts|hibt|hiny|hont|hing|hin|hinr|hi t|hitn|hit|answer|answers|anser|andwer|awnser|anwser|answe|asnwer|answr|amswer|ans|clue|clues|today|todays|todat|tody|tiday|todya|tosay|tofay|tofays|todsy|roday|daily|of (?:the )?day|for the day|solution|solved|what s? the wordle|what is the wordle|first letter|last letter|start letter|start with)\b/.test(
      keyword,
    )
  ) {
    return result("daily");
  }
  if (
    /^(?:the )?wordle$|\bwordle (?:help|puzzle|word|words|search)\b|\bhelp with wordle\b|\bwords for wordle\b/.test(
      keyword,
    )
  ) {
    return result("broad");
  }

  return result(null, "Ambiguous query or intent not supported by the current website");
}

function decodeHtml(value) {
  return value
    .replace(/&#x([\da-f]+);/gi, (_, code) =>
      String.fromCodePoint(Number.parseInt(code, 16)),
    )
    .replace(/&#(\d+);/g, (_, code) => String.fromCodePoint(Number(code)))
    .replace(/&(?:nbsp|#160);/gi, " ")
    .replace(/&amp;/gi, "&")
    .replace(/&apos;|&#39;/gi, "'")
    .replace(/&quot;/gi, '"')
    .replace(/&lt;/gi, "<")
    .replace(/&gt;/gi, ">");
}

function tagText(html, pattern) {
  return decodeHtml(
    [...html.matchAll(pattern)]
      .map((match) => match[1])
      .join(" ")
      .replace(/<[^>]*>/g, " "),
  );
}

function pageSections(html) {
  const withoutNonVisible = html
    .replace(/<script\b[\s\S]*?<\/script>/gi, " ")
    .replace(/<style\b[\s\S]*?<\/style>/gi, " ");
  return {
    title: tagText(html, /<title\b[^>]*>([\s\S]*?)<\/title>/gi),
    description: decodeHtml(
      html.match(
        /<meta\b(?=[^>]*\bname=["']description["'])[^>]*\bcontent=["']([^"']*)["'][^>]*>/i,
      )?.[1] ?? "",
    ),
    h1: tagText(html, /<h1\b[^>]*>([\s\S]*?)<\/h1>/gi),
    headings: tagText(html, /<h[2-3]\b[^>]*>([\s\S]*?)<\/h[2-3]>/gi),
    body: tagText(withoutNonVisible, /<body\b[^>]*>([\s\S]*?)<\/body>/gi),
  };
}

async function loadPages(root) {
  const pages = {};
  for (const { target } of Object.values(PAGE_GROUPS)) {
    if (pages[target]) continue;
    const relative = target === "/" ? "index.html" : `${target.slice(1)}index.html`;
    pages[target] = pageSections(
      await readFile(path.join(root, relative), "utf8"),
    );
  }
  return pages;
}

function exactPlacements(keyword, page) {
  const normalizedKeyword = normalizePhrase(keyword);
  return Object.entries(page)
    .filter(([, value]) => normalizePhrase(value).includes(normalizedKeyword))
    .map(([section]) => section);
}

function coverage(keyword, classification, pages) {
  if (!classification.relevant) return "Excluded";
  const placements = exactPlacements(keyword, pages[classification.target]);
  if (placements.length > 0) return `Exact: ${placements.join(", ")}`;
  return `Natural variation: ${classification.placement}`;
}

function priority(keyword, classification) {
  if (!classification.relevant) return "Excluded";
  if (
    /\b(?:himt|himts|hibt|hiny|hont|hing|hin|hitn|anser|andwer|awnser|anwser|answe|todat|tofay|tofays|todsy)\b/.test(
      normalizePhrase(keyword),
    )
  ) {
    return "Low";
  }
  return classification.group === "broad" ? "Medium" : "High";
}

function csvCell(value) {
  const string = String(value);
  return /[",\r\n]/.test(string) ? `"${string.replaceAll('"', '""')}"` : string;
}

function markdownSummary(rows, exactDuplicateCount, duplicateCount) {
  const count = (predicate) => rows.filter(predicate).length;
  const groups = Object.keys(PAGE_GROUPS)
    .map((group) => `| ${group} | ${count((row) => row.Group === group)} |`)
    .join("\n");
  const exclusions = new Map();
  for (const row of rows.filter((entry) => entry.Group === "Excluded")) {
    exclusions.set(
      row["Exclusion Reason"],
      (exclusions.get(row["Exclusion Reason"]) ?? 0) + 1,
    );
  }
  const exclusionRows = [...exclusions.entries()]
    .sort((left, right) => right[1] - left[1])
    .map(([reason, total]) => `| ${reason} | ${total} |`)
    .join("\n");

  return `# SEO keyword coverage summary

This report is generated from the supplied keyword CSV. Search volume and KD are preserved exactly as source fields, but neither is used for selection, exclusion or priority. Decisions are based on relevance, search intent, usefulness, page fit, existing coverage and the website's actual features.

## Dataset totals

- Original rows analyzed: ${rows.length}
- Exact duplicate rows: ${exactDuplicateCount}
- Exact unique keywords: ${rows.length - exactDuplicateCount}
- Normalized duplicate variants: ${duplicateCount}
- Normalized unique keyword forms: ${rows.length - duplicateCount}
- Relevant rows: ${count((row) => row.Group !== "Excluded")}
- Excluded rows: ${count((row) => row.Group === "Excluded")}
- High-priority relevant rows: ${count((row) => row.Priority === "High")}
- Exact phrases present before: ${count((row) => row["Previous Coverage"].startsWith("Exact:"))}
- Exact phrases present after: ${count((row) => row["Final Coverage"].startsWith("Exact:"))}
- Naturally covered relevant variations after: ${count((row) => row["Final Coverage"].startsWith("Natural variation:"))}

## Relevant groups

| Group | Rows |
| --- | ---: |
${groups}

## Exclusion reasons

| Reason | Rows |
| --- | ---: |
${exclusionRows}

The complete row-level mapping is in \`SEO_KEYWORD_COVERAGE.csv\`.
`;
}

async function main() {
  const { values } = parseArgs({
    options: {
      keywords: { type: "string" },
      baseline: { type: "string" },
      site: { type: "string" },
      output: { type: "string" },
      summary: { type: "string" },
    },
  });
  for (const option of ["keywords", "baseline", "site", "output", "summary"]) {
    if (!values[option]) throw new Error(`Missing --${option}.`);
  }

  const sourceRows = parseCsv(await readFile(values.keywords, "utf8"));
  if (sourceRows.length === 0) throw new Error("Keyword CSV has no data rows.");
  const baselinePages = await loadPages(path.resolve(values.baseline));
  const finalPages = await loadPages(path.resolve(values.site));
  const normalizedCounts = new Map();
  const exactCounts = new Map();
  for (const source of sourceRows) {
    const key = normalizePhrase(source.Keyword);
    normalizedCounts.set(key, (normalizedCounts.get(key) ?? 0) + 1);
    exactCounts.set(source.Keyword, (exactCounts.get(source.Keyword) ?? 0) + 1);
  }
  const exactDuplicateCount = [...exactCounts.values()].reduce(
    (total, count) => total + Math.max(0, count - 1),
    0,
  );
  const duplicateCount = [...normalizedCounts.values()].reduce(
    (total, count) => total + Math.max(0, count - 1),
    0,
  );

  const rows = sourceRows.map((source) => {
    const volume = Number(source["US Monthly Search Volume"]);
    const difficulty = Number(source.KD);
    if (!Number.isInteger(volume) || volume < 0 || !Number.isInteger(difficulty)) {
      throw new Error(`Invalid volume or KD for keyword: ${source.Keyword}`);
    }
    const classification = classifyKeyword(source.Keyword);
    const previousCoverage = coverage(
      source.Keyword,
      classification,
      baselinePages,
    );
    const finalCoverage = coverage(source.Keyword, classification, finalPages);
    return {
      Keyword: source.Keyword,
      "US Volume": volume,
      KD: difficulty,
      Group: classification.group,
      Intent: classification.intent,
      "Target URL": classification.target,
      Priority: priority(source.Keyword, classification),
      "Previous Coverage": previousCoverage,
      "Final Coverage": finalCoverage,
      Placement: finalCoverage.startsWith("Exact:")
        ? finalCoverage.slice("Exact: ".length)
        : classification.placement,
      "Exclusion Reason": classification.exclusionReason,
      "Normalized Duplicate":
        normalizedCounts.get(normalizePhrase(source.Keyword)) > 1 ? "Yes" : "No",
    };
  });

  const headers = Object.keys(rows[0]);
  const csv = `${headers.map(csvCell).join(",")}\n${rows
    .map((row) => headers.map((header) => csvCell(row[header])).join(","))
    .join("\n")}\n`;
  await writeFile(path.resolve(values.output), csv, "utf8");
  await writeFile(
    path.resolve(values.summary),
    markdownSummary(rows, exactDuplicateCount, duplicateCount),
    "utf8",
  );
  process.stdout.write(
    `Keyword audit: ${rows.length} rows; ${rows.filter((row) => row.Group !== "Excluded").length} relevant; ${duplicateCount} normalized duplicates.\n`,
  );
}

const invokedDirectly = process.argv[1]
  ? import.meta.url === pathToFileURL(path.resolve(process.argv[1])).href
  : false;

if (invokedDirectly) {
  await main();
}
