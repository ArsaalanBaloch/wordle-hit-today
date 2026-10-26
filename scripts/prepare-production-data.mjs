import { createHash } from "node:crypto";
import { mkdir, readFile, writeFile } from "node:fs/promises";
import path from "node:path";
import { pathToFileURL } from "node:url";

const POSITIONS = {
  n: { name: "noun", senseType: "1" },
  v: { name: "verb", senseType: "2" },
  a: { name: "adjective", senseType: "3" },
  r: { name: "adverb", senseType: "4" },
  s: { name: "adjective", senseType: "5" },
};

export function fiveLetterVocabulary(standardText, largeText) {
  const candidates = [];
  const seen = new Set();
  for (const text of [standardText, largeText]) {
    for (const rawLine of text.split(/\r?\n/)) {
      const word = rawLine.trim();
      if (!/^[a-z]{5}$/.test(word) || seen.has(word)) continue;
      seen.add(word);
      candidates.push(word.toUpperCase());
    }
  }
  return candidates;
}

export function parseSenseIndex(text) {
  const senses = new Map();
  for (const line of text.split(/\r?\n/)) {
    const match = line.match(
      /^([^%\s]+)%([1-5]):\d{2}:([0-9a-f]{2}):[^\s]*\s+(\d{8})\s+(\d+)\s+(\d+)$/i,
    );
    if (!match) continue;
    const [, lemma, senseType, lexId, offset, senseNumber, tagCount] = match;
    if (!/^[a-z]{5}$/i.test(lemma)) continue;
    senses.set(
      `${lemma.toLowerCase()}|${senseType}|${offset}|${lexId.toLowerCase()}`,
      { senseNumber: Number(senseNumber), tagCount: Number(tagCount) },
    );
  }
  return senses;
}

export function parseWordNetData(text, partCode, senses) {
  if (!POSITIONS[partCode]) {
    throw new Error(`Unsupported WordNet part code: ${partCode}`);
  }
  const entries = [];
  for (const line of text.split(/\r?\n/)) {
    if (!/^\d{8}\s/.test(line)) continue;
    const separator = line.indexOf("|");
    if (separator < 0) continue;
    const fields = line.slice(0, separator).trim().split(/\s+/);
    if (fields.length < 5 || !(fields[2] in POSITIONS)) continue;
    const synsetPartCode = fields[2];
    const position = POSITIONS[synsetPartCode];
    const offset = fields[0];
    const wordCount = Number.parseInt(fields[3], 16);
    if (!Number.isInteger(wordCount) || wordCount < 1) continue;
    const gloss = cleanGloss(line.slice(separator + 1));
    if (gloss.length < 8 || gloss.length > 300) continue;
    for (let index = 0; index < wordCount; index += 1) {
      const lemma = fields[4 + index * 2];
      const rawLexId = fields[5 + index * 2]?.toLowerCase();
      if (
        !/^[a-z]{5}$/i.test(lemma ?? "") ||
        !/^[0-9a-f]{1,2}$/.test(rawLexId ?? "")
      ) {
        continue;
      }
      const lexId = rawLexId.padStart(2, "0");
      const normalizedLemma = lemma.toLowerCase();
      const sense = senses.get(
        `${normalizedLemma}|${position.senseType}|${offset}|${lexId}`,
      ) ?? { senseNumber: 999, tagCount: 0 };
      entries.push({
        lemma: normalizedLemma.toUpperCase(),
        part_of_speech: position.name,
        sense_id: `wn30-${synsetPartCode}-${offset}-${lexId}`,
        gloss,
        _sense_number: sense.senseNumber,
        _tag_count: sense.tagCount,
      });
    }
  }
  return entries;
}

export function cleanGloss(rawGloss) {
  return rawGloss
    .replace(/;\s*"[^"]*"/g, "")
    .replace(/\s+/g, " ")
    .trim()
    .replace(/[.;]\s*$/, "")
    .trim();
}

function parseArguments(argv) {
  const values = {};
  for (const value of argv) {
    const match = value.match(/^--([a-z-]+)=(.+)$/);
    if (match) values[match[1]] = path.resolve(match[2]);
  }
  for (const required of ["esdb-dir", "wordnet-dict", "output-dir"]) {
    if (!values[required]) {
      throw new Error(`Missing --${required}=PATH.`);
    }
  }
  return values;
}

function sha256(contents) {
  return createHash("sha256").update(contents).digest("hex");
}

async function main() {
  const options = parseArguments(process.argv.slice(2));
  const standardPath = path.join(options["esdb-dir"], "en_US.txt");
  const largePath = path.join(options["esdb-dir"], "en_US-large.txt");
  const sensePath = path.join(options["wordnet-dict"], "index.sense");
  const [standard, large, senseIndex] = await Promise.all([
    readFile(standardPath, "utf8"),
    readFile(largePath, "utf8"),
    readFile(sensePath, "utf8"),
  ]);
  const words = fiveLetterVocabulary(standard, large);
  if (words.length < 1_000) {
    throw new Error(
      `Only ${words.length} five-letter ESDB words were produced.`,
    );
  }

  const senses = parseSenseIndex(senseIndex);
  const definitionSources = ["noun", "verb", "adj", "adv"];
  const partCodes = { noun: "n", verb: "v", adj: "a", adv: "r" };
  const definitionFiles = await Promise.all(
    definitionSources.map(async (name) => {
      const sourcePath = path.join(options["wordnet-dict"], `data.${name}`);
      const contents = await readFile(sourcePath, "utf8");
      return { name, sourcePath, contents };
    }),
  );
  const definitions = definitionFiles
    .flatMap(({ name, contents }) =>
      parseWordNetData(contents, partCodes[name], senses),
    )
    .sort(
      (left, right) =>
        left.lemma.localeCompare(right.lemma, "en") ||
        left._sense_number - right._sense_number ||
        right._tag_count - left._tag_count ||
        left.sense_id.localeCompare(right.sense_id, "en"),
    )
    .map(({ _sense_number, _tag_count, ...entry }) => entry);
  if (definitions.length === 0) {
    throw new Error("No five-letter WordNet definitions were produced.");
  }

  await mkdir(options["output-dir"], { recursive: true });
  const vocabularyOutput = `${words.join("\n")}\n`;
  const definitionsOutput = `${JSON.stringify(definitions)}\n`;
  await Promise.all([
    writeFile(
      path.join(options["output-dir"], "esdb-en-us-five-letter.txt"),
      vocabularyOutput,
      "utf8",
    ),
    writeFile(
      path.join(options["output-dir"], "wordnet-3.0-five-letter.json"),
      definitionsOutput,
      "utf8",
    ),
    writeFile(
      path.join(options["output-dir"], "data-manifest.json"),
      `${JSON.stringify(
        {
          schema_version: 1,
          esdb: {
            version: "2026.02.25",
            standard_sha256: sha256(standard),
            large_sha256: sha256(large),
            output_sha256: sha256(vocabularyOutput),
            five_letter_words: words.length,
          },
          wordnet: {
            version: "3.0",
            index_sense_sha256: sha256(senseIndex),
            data_sha256: Object.fromEntries(
              definitionFiles.map(({ name, contents }) => [
                name,
                sha256(contents),
              ]),
            ),
            output_sha256: sha256(definitionsOutput),
            definition_rows: definitions.length,
          },
        },
        null,
        2,
      )}\n`,
      "utf8",
    ),
  ]);
  process.stdout.write(
    `Prepared ${words.length} vocabulary words and ${definitions.length} WordNet rows.\n`,
  );
}

const invokedPath = process.argv[1]
  ? pathToFileURL(path.resolve(process.argv[1])).href
  : "";
if (import.meta.url === invokedPath) {
  main().catch((error) => {
    process.stderr.write(`${error.message}\n`);
    process.exitCode = 1;
  });
}
