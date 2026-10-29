const ISO_DATE = /^\d{4}-\d{2}-\d{2}$/;

export function localDateParts(
  now = new Date(),
  timezone = Intl.DateTimeFormat().resolvedOptions().timeZone,
) {
  const parts = new Intl.DateTimeFormat("en-US", {
    timeZone: timezone,
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).formatToParts(now);
  const values = Object.fromEntries(
    parts.map(({ type, value }) => [type, value]),
  );
  return { timezone, date: `${values.year}-${values.month}-${values.day}` };
}

export function dateRelation(pageDate, localDate) {
  if (!ISO_DATE.test(pageDate) || !ISO_DATE.test(localDate)) return null;
  if (pageDate < localDate) return "past";
  if (pageDate > localDate) return "future";
  return "today";
}

function initializeAnswerReveals(root = document) {
  for (const answer of root.querySelectorAll("[data-answer]")) {
    const noJavaScriptAnswer = answer.querySelector("[data-no-js-answer]");
    if (noJavaScriptAnswer) noJavaScriptAnswer.hidden = true;
    const javaScriptControls = answer.querySelector(
      "[data-js-answer-controls]",
    );
    if (javaScriptControls) javaScriptControls.hidden = false;
    if (answer.dataset.revealReady === "true") continue;
    answer.dataset.revealReady = "true";
    const solution = answer.dataset.answer;
    if (!/^[A-Z]{5}$/.test(solution ?? "")) continue;
    const tiles = [...answer.querySelectorAll("[data-letter-index]")];
    const explanation = answer.querySelector("[data-answer-explanation]");
    const showExplanationWhenComplete = () => {
      if (tiles.every((tile) => tile.dataset.revealed === "true")) {
        if (explanation) explanation.hidden = false;
      }
    };
    const revealLetter = (tile) => {
      const index = Number(tile.dataset.letterIndex);
      tile.textContent = solution[index];
      tile.dataset.revealed = "true";
      tile.setAttribute(
        "aria-label",
        `Letter ${index + 1}: ${solution[index]}`,
      );
      showExplanationWhenComplete();
    };
    for (const tile of tiles)
      tile.addEventListener("click", () => revealLetter(tile));
    answer
      .querySelector("[data-reveal-answer]")
      ?.addEventListener("click", (event) => {
        for (const tile of tiles) revealLetter(tile);
        event.currentTarget.hidden = true;
      });
  }
}

function resetAnswerReveals(root = document) {
  for (const answer of root.querySelectorAll("[data-answer]")) {
    for (const tile of answer.querySelectorAll("[data-letter-index]")) {
      const index = Number(tile.dataset.letterIndex);
      tile.textContent = "?";
      delete tile.dataset.revealed;
      tile.setAttribute("aria-label", `Reveal letter ${index + 1}`);
    }
    const full = answer.querySelector("[data-reveal-answer]");
    if (full) full.hidden = false;
    const explanation = answer.querySelector("[data-answer-explanation]");
    if (explanation) explanation.hidden = true;
  }
}

function initializeSharing(root = document) {
  for (const button of root.querySelectorAll("[data-share]")) {
    if (button.dataset.shareReady === "true") continue;
    button.dataset.shareReady = "true";
    button.addEventListener("click", async () => {
      const text = button.dataset.share;
      const fallback = button.parentElement?.querySelector(
        "[data-share-fallback]",
      );
      try {
        if (navigator.share) {
          await navigator.share({ text });
        } else if (navigator.clipboard?.writeText) {
          await navigator.clipboard.writeText(text);
          button.textContent = "Copied";
        } else {
          throw new Error("Sharing is unavailable");
        }
      } catch (error) {
        if (error?.name === "AbortError") return;
        if (fallback) fallback.hidden = false;
      }
    });
  }
}

function initializeDatePickers(root = document) {
  for (const form of root.querySelectorAll("[data-date-picker]")) {
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      const date = new FormData(form).get("date");
      if (typeof date === "string" && ISO_DATE.test(date)) {
        window.location.assign(`/wordle-answers/${date}/`);
      }
    });
  }
}

function initializeNavigation(root = document) {
  const menu = root.querySelector(".nav-menu");
  if (!menu || typeof window.matchMedia !== "function") return;
  const mobile = window.matchMedia("(max-width: 47.999rem)");
  const sync = () => {
    if (mobile.matches) menu.removeAttribute("open");
    else menu.setAttribute("open", "");
  };
  sync();
  mobile.addEventListener?.("change", sync);
}

async function initializeTodayPage() {
  const root = document.querySelector("[data-today-root]");
  if (!root) return;
  const content = root.querySelector("[data-today-content]");
  const status = root.querySelector("[data-date-status]");
  const retry = root.querySelector("[data-date-retry]");
  const notice = root.querySelector("[data-date-notice]");
  const heading = root.querySelector("[data-puzzle-heading]");
  const summary = root.querySelector("[data-puzzle-summary]");
  const labelledDate = root.dataset.homeDate;
  const labelledFullDate = root.dataset.homeFullDate;
  let resolved;

  const showNotice = (message = "") => {
    notice.textContent = message;
    notice.hidden = message === "";
  };
  const initializePuzzleControls = () => {
    initializeAnswerReveals(content);
    initializeSharing(content);
  };
  const showLabelledFallback = (statusMessage, noticeMessage) => {
    root.dataset.displayedDate = labelledDate;
    if (heading)
      heading.textContent = `Wordle Hints and Answer — ${labelledFullDate}`;
    status.textContent = statusMessage;
    showNotice(noticeMessage);
    initializePuzzleControls();
  };
  const showLocalPuzzle = (fragment) => {
    const date = fragment.dataset.puzzleDate;
    const number = fragment.dataset.puzzleNumber;
    const fullDate = fragment.dataset.puzzleFullDate;
    if (
      date !== resolved.date ||
      !/^\d+$/.test(number ?? "") ||
      typeof fullDate !== "string" ||
      fullDate === ""
    ) {
      throw new Error("Published puzzle fragment does not match local date");
    }
    content.replaceChildren(document.importNode(fragment, true));
    content.setAttribute("aria-label", `Wordle hints for ${fullDate}`);
    root.dataset.displayedDate = date;
    if (heading)
      heading.textContent = `Today’s Wordle Hints and Answer — ${fullDate}`;
    if (summary)
      summary.textContent = `Wordle #${number} · Progressive clues with the answer hidden until you choose to reveal it.`;
    status.textContent = `Showing the published ${date} puzzle for this device (${resolved.timezone}).`;
    showNotice();
    initializePuzzleControls();
  };

  try {
    resolved = localDateParts();
  } catch {
    showLabelledFallback(
      `Automatic local-date selection is unavailable. Showing the explicitly dated ${labelledDate} puzzle.`,
      "Your browser did not provide local timezone support. The explicitly dated UTC fallback remains available.",
    );
    return;
  }

  root.dataset.resolvedDate = resolved.date;
  if (resolved.date === labelledDate) {
    root.dataset.displayedDate = labelledDate;
    status.textContent = `Showing the published ${resolved.date} puzzle for this device (${resolved.timezone}).`;
    initializePuzzleControls();
    return;
  }

  let retryCount = 0;
  const checkManifest = async () => {
    let localPuzzleLoaded = false;
    retry.disabled = true;
    showNotice();
    try {
      const response = await fetch("/assets/data/published-dates.json", {
        headers: { Accept: "application/json" },
        cache: "no-cache",
      });
      if (!response.ok) throw new Error(`Manifest returned ${response.status}`);
      const manifest = await response.json();
      if (
        manifest.schema_version !== 1 ||
        !Array.isArray(manifest.available_dates)
      ) {
        throw new Error("Unsupported manifest");
      }
      const target = manifest.available_dates.find(
        (entry) => entry.date === resolved.date,
      );
      if (
        target &&
        ISO_DATE.test(target.date) &&
        target.url === `/wordle-answers/${target.date}/`
      ) {
        const puzzleResponse = await fetch(target.url, {
          headers: { Accept: "text/html" },
          cache: "no-cache",
        });
        if (!puzzleResponse.ok)
          throw new Error(`Puzzle page returned ${puzzleResponse.status}`);
        const puzzleDocument = new DOMParser().parseFromString(
          await puzzleResponse.text(),
          "text/html",
        );
        const fragment = puzzleDocument.querySelector("[data-puzzle-fragment]");
        if (!fragment) throw new Error("Puzzle fragment is missing");
        showLocalPuzzle(fragment);
        localPuzzleLoaded = true;
        return;
      }
      showLabelledFallback(
        `Hints for this device’s local date (${resolved.date}) are not available yet. Showing the explicitly dated ${labelledDate} puzzle instead.`,
        `The ${resolved.date} puzzle has not been published. The available puzzle below is labelled ${labelledDate}; you can retry or choose another date.`,
      );
    } catch {
      showLabelledFallback(
        `Could not load the published puzzle for ${resolved.date}. Showing the explicitly dated ${labelledDate} puzzle.`,
        "The local-date check failed. The available fallback remains clearly dated, or you can choose a date manually.",
      );
    } finally {
      retry.hidden = localPuzzleLoaded || retryCount >= 2;
      retry.disabled = false;
    }
  };
  retry.addEventListener("click", () => {
    retryCount += 1;
    checkManifest();
  });
  await checkManifest();
}

function initializeDatedPage() {
  const page = document.querySelector("[data-dated-page]");
  if (!page) return;
  try {
    const { date } = localDateParts();
    const relation = dateRelation(page.dataset.pageDate, date);
    const label = page.querySelector("[data-date-relation]");
    const warning = page.querySelector("[data-future-date-warning]");
    if (label) {
      label.textContent =
        relation === "today"
          ? "Today’s Wordle puzzle"
          : relation === "past"
            ? "Historical Wordle puzzle"
            : "Published for an earlier timezone";
    }
    if (warning && relation === "future") {
      warning.textContent = `This verified puzzle is dated ${page.dataset.pageDate}, which is later than this device’s local date (${date}). Continue only if you intentionally selected it.`;
      warning.hidden = false;
    }
    const link = page.querySelector("[data-local-today-link]");
    if (link && date !== page.dataset.pageDate) {
      link.href = `/wordle-answers/${date}/`;
      link.hidden = false;
    }
  } catch {
    // The explicit dated page remains authoritative when Intl is missing.
  }
}

function initializeArchiveDateRelations(root = document) {
  let localDate;
  try {
    localDate = localDateParts().date;
  } catch {
    return;
  }
  for (const label of root.querySelectorAll("[data-archive-date]")) {
    const relation = dateRelation(label.dataset.archiveDate, localDate);
    if (!relation) continue;
    label.textContent =
      relation === "today"
        ? "Today in your timezone"
        : relation === "past"
          ? "Past puzzle"
          : "Later than your local date";
    label.hidden = false;
  }
}

function watchForDateChange() {
  const page = document.querySelector("[data-today-root], [data-dated-page]");
  const update = page?.querySelector("[data-date-update]");
  if (!page || !update) return;

  let initial;
  try {
    initial = localDateParts().date;
  } catch {
    return;
  }
  const check = () => {
    try {
      const current = localDateParts().date;
      if (current !== initial) {
        initial = current;
        if (page.matches("[data-today-root]")) resetAnswerReveals(page);
        update.href = "/";
        update.textContent = "Refresh for your new local date";
        update.hidden = false;
      }
    } catch {
      // Existing explicitly dated content remains usable.
    }
  };
  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "visible") check();
  });
  window.setInterval(check, 60_000);
}

if (typeof document !== "undefined") {
  initializeNavigation();
  initializeDatePickers();
  initializeSharing();
  initializeAnswerReveals(
    document.querySelector("[data-dated-page]") ??
      document.createDocumentFragment(),
  );
  initializeTodayPage();
  initializeDatedPage();
  initializeArchiveDateRelations();
  watchForDateChange();
}
