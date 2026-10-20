import { build, transform } from "esbuild";
import { createHash } from "node:crypto";
import { cp, mkdir, readFile, rm, writeFile } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const projectRoot = path.dirname(scriptDirectory);
const outputDirectory = path.join(projectRoot, "assets", "dist");

const digest = (contents) =>
  createHash("sha256").update(contents).digest("hex").slice(0, 12);

const publicPath = (relativePath) =>
  `/assets/static/${relativePath.replaceAll("\\", "/")}`;

await rm(outputDirectory, { recursive: true, force: true });
await mkdir(path.join(outputDirectory, "fonts"), { recursive: true });
await mkdir(path.join(outputDirectory, "licenses"), { recursive: true });

const fontSources = {
  inter400: "node_modules/@fontsource/inter/files/inter-latin-400-normal.woff2",
  inter600: "node_modules/@fontsource/inter/files/inter-latin-600-normal.woff2",
  manrope600:
    "node_modules/@fontsource/manrope/files/manrope-latin-600-normal.woff2",
  manrope700:
    "node_modules/@fontsource/manrope/files/manrope-latin-700-normal.woff2",
};

const fonts = {};
for (const [key, relativeSource] of Object.entries(fontSources)) {
  const contents = await readFile(path.join(projectRoot, relativeSource));
  const filename = `${key}.${digest(contents)}.woff2`;
  await writeFile(path.join(outputDirectory, "fonts", filename), contents);
  fonts[key] = publicPath(`fonts/${filename}`);
}

await cp(
  path.join(projectRoot, "node_modules", "@fontsource", "inter", "LICENSE"),
  path.join(outputDirectory, "licenses", "Inter-OFL-1.1.txt"),
);
await cp(
  path.join(projectRoot, "node_modules", "@fontsource", "manrope", "LICENSE"),
  path.join(outputDirectory, "licenses", "Manrope-OFL-1.1.txt"),
);

let css = await readFile(
  path.join(projectRoot, "assets", "css", "site.css"),
  "utf8",
);
for (const [key, assetPath] of Object.entries(fonts)) {
  css = css.replaceAll(`__${key.toUpperCase()}__`, assetPath);
}
const cssResult = await transform(css, {
  loader: "css",
  minify: true,
  target: "es2022",
});
const cssFilename = `site.${digest(cssResult.code)}.css`;
await writeFile(path.join(outputDirectory, cssFilename), cssResult.code);

const scripts = {};
for (const entryName of ["main", "solver", "archive"]) {
  const result = await build({
    entryPoints: [path.join(projectRoot, "assets", "js", `${entryName}.js`)],
    bundle: true,
    minify: true,
    format: "esm",
    platform: "browser",
    target: ["es2022"],
    write: false,
    legalComments: "none",
  });
  const contents = result.outputFiles[0].contents;
  const filename = `${entryName}.${digest(contents)}.js`;
  await writeFile(path.join(outputDirectory, filename), contents);
  scripts[entryName] = publicPath(filename);
}

const configuredVocabularyPath = process.env.WORDLE_VOCABULARY_PATH;
const vocabularyPath = configuredVocabularyPath
  ? path.resolve(configuredVocabularyPath)
  : path.join(projectRoot, "resources", "vocabulary", "fixture.json");
const vocabularySource = JSON.parse(await readFile(vocabularyPath, "utf8"));
if (
  !Array.isArray(vocabularySource.candidates) ||
  vocabularySource.candidates.length === 0
) {
  throw new Error("Vocabulary must contain at least one candidate.");
}
if (!["fixture", "production"].includes(vocabularySource.mode)) {
  throw new Error("Vocabulary mode must be fixture or production.");
}
if (configuredVocabularyPath && vocabularySource.mode !== "production") {
  throw new Error(
    "WORDLE_VOCABULARY_PATH must point to an imported production vocabulary.",
  );
}
for (const candidate of vocabularySource.candidates) {
  if (
    !/^[A-Z]{5}$/.test(candidate.word) ||
    !Number.isInteger(candidate.rank) ||
    candidate.rank < 1
  ) {
    throw new Error(
      "Vocabulary candidates require an uppercase five-letter word and positive rank.",
    );
  }
}
const vocabularyContents = `${JSON.stringify(vocabularySource)}\n`;
const vocabularyFilename = `solver-${vocabularySource.mode}.${digest(vocabularyContents)}.json`;
await writeFile(
  path.join(outputDirectory, vocabularyFilename),
  vocabularyContents,
);

const manifest = {
  schema_version: 1,
  css: publicPath(cssFilename),
  scripts,
  fonts,
  vocabulary: publicPath(vocabularyFilename),
  vocabulary_mode: vocabularySource.mode,
  licenses: {
    inter: publicPath("licenses/Inter-OFL-1.1.txt"),
    manrope: publicPath("licenses/Manrope-OFL-1.1.txt"),
  },
};

await writeFile(
  path.join(outputDirectory, "manifest.json"),
  `${JSON.stringify(manifest, null, 2)}\n`,
);

const sizes = {
  css: Buffer.byteLength(cssResult.code),
  main_js: (
    await readFile(path.join(outputDirectory, path.basename(scripts.main)))
  ).byteLength,
  solver_js: (
    await readFile(path.join(outputDirectory, path.basename(scripts.solver)))
  ).byteLength,
  archive_js: (
    await readFile(path.join(outputDirectory, path.basename(scripts.archive)))
  ).byteLength,
  vocabulary: Buffer.byteLength(vocabularyContents),
};

process.stdout.write(
  `${JSON.stringify({ outputDirectory, sizes }, null, 2)}\n`,
);
