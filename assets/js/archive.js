const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/;
const YEAR = /^\d{4}$/;
const INDEX_PATH = /^\/assets\/data\/archive-(\d{4})\.[a-f0-9]{12}\.json$/;

const archive =
  typeof document === "undefined"
    ? null
    : document.querySelector("[data-archive-search]");
if (archive) initializeArchiveSearch(archive);

export function archiveQueryType(query) {
  if (isIsoDate(query)) return "date";
  if (/^\d+$/.test(query) && Number.isSafeInteger(Number(query)))
    return "number";
  if (/^[A-Za-z]{5}$/.test(query)) return "word";
  return null;
}

export function parseArchiveManifest(payload) {
  if (
    !isObject(payload) ||
    payload.schema_version !== 1 ||
    !isObject(payload.archive_indexes)
  ) {
    throw new Error("Unsupported archive manifest");
  }

  const indexes = new Map();
  for (const [year, url] of Object.entries(payload.archive_indexes)) {
    const match = typeof url === "string" ? INDEX_PATH.exec(url) : null;
    if (!YEAR.test(year) || !match || match[1] !== year) {
      throw new Error("Archive manifest contains an unsafe index path");
    }
    indexes.set(year, url);
  }

  return indexes;
}

export function archivePartitionsForQuery(indexes, queryType, query) {
  if (queryType === "date") {
    const year = query.slice(0, 4);
    const url = indexes.get(year);
    return url ? [[year, url]] : [];
  }

  return [...indexes.entries()];
}

export function parseArchivePartition(payload, expectedYear) {
  if (!Array.isArray(payload) || !YEAR.test(expectedYear)) {
    throw new Error("Archive partition has an unsupported format");
  }

  return payload.map((entry) => {
    if (
      !isObject(entry) ||
      !isIsoDate(entry.date) ||
      !entry.date.startsWith(`${expectedYear}-`) ||
      !Number.isSafeInteger(entry.number) ||
      entry.number < 0 ||
      typeof entry.word !== "string" ||
      !/^[A-Z]{5}$/.test(entry.word) ||
      entry.url !== `/wordle-answers/${entry.date}/`
    ) {
      throw new Error("Archive partition contains an invalid entry");
    }

    return {
      date: entry.date,
      number: entry.number,
      word: entry.word,
      url: entry.url,
    };
  });
}

async function initializeArchiveSearch(root) {
  const form = root.querySelector("form");
  const input = root.querySelector("input[type='search']");
  const status = root.querySelector("[data-search-status]");
  const results = root.querySelector("[data-search-results]");
  const defaultArchive = document.querySelector("[data-default-archive]");
  const partitionCache = new Map();
  let controller;

  const loadPartition = async (year, url, signal) => {
    throwIfAborted(signal);
    if (partitionCache.has(url)) return partitionCache.get(url);

    const response = await fetch(url, {
      headers: { Accept: "application/json" },
      signal,
    });
    if (!response.ok) throw new Error("Archive partition unavailable");
    const entries = parseArchivePartition(await response.json(), year);
    throwIfAborted(signal);
    partitionCache.set(url, entries);
    return entries;
  };

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    controller?.abort();
    controller = undefined;

    const query = input.value.trim();
    if (!query) {
      results.replaceChildren();
      status.textContent = "Showing the current archive.";
      defaultArchive.hidden = false;
      return;
    }

    const queryType = archiveQueryType(query);
    if (!queryType) {
      results.replaceChildren();
      status.textContent =
        "Use an ISO date (YYYY-MM-DD), a puzzle number, or an exact five-letter word.";
      defaultArchive.hidden = false;
      return;
    }

    const requestController = new AbortController();
    controller = requestController;
    defaultArchive.hidden = true;
    results.replaceChildren();
    status.textContent = "Searching the published archive…";

    try {
      const manifestResponse = await fetch(
        "/assets/data/published-dates.json",
        {
          headers: { Accept: "application/json" },
          signal: requestController.signal,
        },
      );
      if (!manifestResponse.ok) throw new Error("Manifest unavailable");
      const indexes = parseArchiveManifest(await manifestResponse.json());
      const partitions = archivePartitionsForQuery(indexes, queryType, query);
      const matches = [];
      for (const [index, [year, url]] of partitions.entries()) {
        status.textContent = `Searching archive partition ${index + 1} of ${partitions.length}…`;
        const entries = await loadPartition(
          year,
          url,
          requestController.signal,
        );
        matches.push(
          ...entries.filter((entry) => {
            if (queryType === "date") return entry.date === query;
            if (queryType === "number") return String(entry.number) === query;
            return entry.word === query.toUpperCase();
          }),
        );
      }

      throwIfAborted(requestController.signal);
      results.replaceChildren(
        ...matches.map((entry) => {
          const item = document.createElement("li");
          const link = document.createElement("a");
          link.href = entry.url;
          link.textContent = `Wordle #${entry.number} — ${entry.date}`;
          item.append(link);
          return item;
        }),
      );
      status.textContent = matches.length
        ? `${matches.length} exact match${matches.length === 1 ? "" : "es"}.`
        : "No exact match was found in the published archive.";
    } catch (error) {
      if (error?.name !== "AbortError") {
        status.textContent =
          "Archive search could not finish. Ordinary archive links remain available below.";
        defaultArchive.hidden = false;
      }
    } finally {
      if (controller === requestController) controller = undefined;
    }
  });
}

function isIsoDate(value) {
  if (typeof value !== "string" || !ISO_DATE.test(value)) return false;
  const parsed = new Date(`${value}T00:00:00Z`);
  return (
    !Number.isNaN(parsed.valueOf()) &&
    parsed.toISOString().slice(0, 10) === value
  );
}

function isObject(value) {
  return value !== null && typeof value === "object" && !Array.isArray(value);
}

function throwIfAborted(signal) {
  if (!signal.aborted) return;
  const error = new Error("Archive search was aborted");
  error.name = "AbortError";
  throw error;
}
