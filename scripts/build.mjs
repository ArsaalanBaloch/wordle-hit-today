import { build, transform } from "esbuild";
import { createHash } from "node:crypto";
import { mkdir, readFile, rename, rm, writeFile } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";

const scriptDirectory = path.dirname(fileURLToPath(import.meta.url));
const projectRoot = path.dirname(scriptDirectory);
const outputDirectory = path.join(projectRoot, "assets", "dist");
const outputParent = path.dirname(outputDirectory);
const buildNonce = `${process.pid}-${Date.now()}`;
const stageDirectory = path.join(outputParent, `.dist-stage-${buildNonce}`);
const previousDirectory = path.join(
  outputParent,
  `.dist-previous-${buildNonce}`,
);

let buildReport;

try {
  const digest = (contents) =>
    createHash("sha256").update(contents).digest("hex").slice(0, 12);

  const publicPath = (relativePath) =>
    `/assets/static/${relativePath.replaceAll("\\", "/")}`;

  await rm(stageDirectory, { recursive: true, force: true });
  await mkdir(path.join(stageDirectory, "fonts"), { recursive: true });
  await mkdir(path.join(stageDirectory, "licenses"), { recursive: true });

  const fontSources = {
    inter400:
      "node_modules/@fontsource/inter/files/inter-latin-400-normal.woff2",
    inter600:
      "node_modules/@fontsource/inter/files/inter-latin-600-normal.woff2",
    manrope700:
      "node_modules/@fontsource/manrope/files/manrope-latin-700-normal.woff2",
  };

  const fonts = {};
  for (const [key, relativeSource] of Object.entries(fontSources)) {
    const contents = await readFile(path.join(projectRoot, relativeSource));
    const filename = `${key}.${digest(contents)}.woff2`;
    await writeFile(path.join(stageDirectory, "fonts", filename), contents);
    fonts[key] = publicPath(`fonts/${filename}`);
  }

  const licenseSources = {
    inter: ["node_modules/@fontsource/inter/LICENSE", "Inter-OFL-1.1"],
    manrope: ["node_modules/@fontsource/manrope/LICENSE", "Manrope-OFL-1.1"],
  };
  const licenses = {};
  for (const [key, [relativeSource, basename]] of Object.entries(
    licenseSources,
  )) {
    const contents = await readFile(path.join(projectRoot, relativeSource));
    const filename = `${basename}.${digest(contents)}.txt`;
    await writeFile(path.join(stageDirectory, "licenses", filename), contents);
    licenses[key] = publicPath(`licenses/${filename}`);
  }

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
  await writeFile(path.join(stageDirectory, cssFilename), cssResult.code);

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
    await writeFile(path.join(stageDirectory, filename), contents);
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
    path.join(stageDirectory, vocabularyFilename),
    vocabularyContents,
  );

  const manifest = {
    schema_version: 1,
    css: publicPath(cssFilename),
    scripts,
    fonts,
    vocabulary: publicPath(vocabularyFilename),
    vocabulary_mode: vocabularySource.mode,
    licenses,
  };

  await writeFile(
    path.join(stageDirectory, "manifest.json"),
    `${JSON.stringify(manifest, null, 2)}\n`,
  );

  const sizes = {
    css: Buffer.byteLength(cssResult.code),
    main_js: (
      await readFile(path.join(stageDirectory, path.basename(scripts.main)))
    ).byteLength,
    solver_js: (
      await readFile(path.join(stageDirectory, path.basename(scripts.solver)))
    ).byteLength,
    archive_js: (
      await readFile(path.join(stageDirectory, path.basename(scripts.archive)))
    ).byteLength,
    vocabulary: Buffer.byteLength(vocabularyContents),
  };

  for (const [name, bytes] of Object.entries(sizes)) {
    if (name !== "vocabulary" && bytes > 30 * 1024) {
      throw new Error(`${name} exceeds the 30 KiB uncompressed asset budget.`);
    }
  }

  let retainedPrevious = false;
  let promoted = false;
  try {
    try {
      await rename(outputDirectory, previousDirectory);
      retainedPrevious = true;
    } catch (error) {
      if (error?.code !== "ENOENT") throw error;
    }
    await rename(stageDirectory, outputDirectory);
    promoted = true;
  } catch (error) {
    if (retainedPrevious && !promoted) {
      try {
        await rename(previousDirectory, outputDirectory);
      } catch {
        // Preserve the original promotion error; recovery remains in .previous.
      }
    }
    throw error;
  }
  if (retainedPrevious) {
    try {
      await rm(previousDirectory, { recursive: true, force: true });
    } catch {
      process.stderr.write(
        `Warning: the previous asset build remains at ${previousDirectory}.\n`,
      );
    }
  }

  buildReport = { outputDirectory, sizes };
} finally {
  await rm(stageDirectory, { recursive: true, force: true });
}

process.stdout.write(`${JSON.stringify(buildReport, null, 2)}\n`);
