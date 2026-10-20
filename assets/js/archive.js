const archive = document.querySelector("[data-archive-search]");
if (archive) initializeArchiveSearch(archive);

async function initializeArchiveSearch(root) {
  const form = root.querySelector("form");
  const input = root.querySelector("input[type='search']");
  const status = root.querySelector("[data-search-status]");
  const results = root.querySelector("[data-search-results]");
  const defaultArchive = document.querySelector("[data-default-archive]");
  let controller;

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const query = input.value.trim();
    if (!query) {
      results.replaceChildren();
      status.textContent = "Showing the current archive.";
      defaultArchive.hidden = false;
      return;
    }

    const queryType = /^\d{4}-\d{2}-\d{2}$/.test(query)
      ? "date"
      : /^\d+$/.test(query)
        ? "number"
        : /^[A-Za-z]{5}$/.test(query)
          ? "word"
          : null;
    if (!queryType) {
      status.textContent =
        "Use an ISO date (YYYY-MM-DD), a puzzle number, or an exact five-letter word.";
      return;
    }

    controller?.abort();
    controller = new AbortController();
    defaultArchive.hidden = true;
    status.textContent = "Searching the published archive…";

    try {
      const manifestResponse = await fetch(
        "/assets/data/published-dates.json",
        {
          signal: controller.signal,
        },
      );
      if (!manifestResponse.ok) throw new Error("Manifest unavailable");
      const manifest = await manifestResponse.json();
      const indexes = Object.values(manifest.archive_indexes ?? {});
      const matches = [];
      for (const [index, url] of indexes.entries()) {
        status.textContent = `Searching archive partition ${index + 1} of ${indexes.length}…`;
        const response = await fetch(url, { signal: controller.signal });
        if (!response.ok) throw new Error("Archive partition unavailable");
        const entries = await response.json();
        matches.push(
          ...entries.filter((entry) => {
            if (queryType === "date") return entry.date === query;
            if (queryType === "number") return String(entry.number) === query;
            return entry.word === query.toUpperCase();
          }),
        );
      }

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
      if (error.name !== "AbortError") {
        status.textContent =
          "Archive search could not finish. Ordinary archive links remain available below.";
        defaultArchive.hidden = false;
      }
    }
  });
}
