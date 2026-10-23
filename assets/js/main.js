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

function initializeAnswerReveals(root = document) {
  for (const answer of root.querySelectorAll("[data-answer]")) {
    if (answer.dataset.revealReady === "true") continue;
    answer.dataset.revealReady = "true";
    const solution = answer.dataset.answer;
    if (!/^[A-Z]{5}$/.test(solution ?? "")) continue;
    const tiles = [...answer.querySelectorAll("[data-letter-index]")];
    const revealLetter = (tile) => {
      const index = Number(tile.dataset.letterIndex);
      tile.textContent = solution[index];
      tile.dataset.revealed = "true";
      tile.setAttribute(
        "aria-label",
        `Letter ${index + 1}: ${solution[index]}`,
      );
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
  }
}

function initializeSharing(root = document) {
  for (const button of root.querySelectorAll("[data-share]")) {
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
  const labelledDate = root.dataset.homeDate;
  let resolved;

  try {
    resolved = localDateParts();
  } catch {
    status.textContent =
      "Automatic local-date selection is unavailable. The UTC-labelled fallback remains available below.";
    root.querySelector("[data-intl-error]").hidden = false;
    content.hidden = false;
    content.inert = false;
    initializeAnswerReveals(content);
    return;
  }

  root.dataset.resolvedDate = resolved.date;
  status.textContent = `Using ${resolved.date} on this device (${resolved.timezone}).`;
  if (resolved.date === labelledDate) {
    content.hidden = false;
    content.inert = false;
    initializeAnswerReveals(content);
    return;
  }

  let retryCount = 0;
  const checkManifest = async () => {
    retry.disabled = true;
    root.querySelector("[data-pending]").hidden = true;
    root.querySelector("[data-manifest-error]").hidden = true;
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
        window.location.replace(target.url);
        return;
      }
      status.textContent = `Hints for ${resolved.date} are not available yet.`;
      root.querySelector("[data-pending]").hidden = false;
    } catch {
      status.textContent = `Could not check whether hints for ${resolved.date} are available. The page is labelled for ${labelledDate}.`;
      root.querySelector("[data-manifest-error]").hidden = false;
      content.hidden = false;
      content.inert = false;
      initializeAnswerReveals(content);
    } finally {
      retry.hidden = retryCount >= 2;
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
    const link = page.querySelector("[data-local-today-link]");
    if (link && date !== page.dataset.pageDate) {
      link.href = `/wordle-answers/${date}/`;
      link.hidden = false;
    }
  } catch {
    // The explicit dated page remains authoritative when Intl is missing.
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
        update.href = `/wordle-answers/${current}/`;
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
  watchForDateChange();
}
