import {
  feedbackStates,
  filterCandidates,
  rankCandidates,
} from "./feedback.js";

const solver = document.querySelector("[data-solver]");
if (solver) initializeSolver(solver);

async function initializeSolver(root) {
  const status = root.querySelector("[data-solver-status]");
  const results = root.querySelector("[data-results]");
  const count = root.querySelector("[data-candidate-count]");
  const warning = root.querySelector("[data-vocabulary-warning]");
  const retry = root.querySelector("[data-dictionary-retry]");
  const loadMore = root.querySelector("[data-load-more]");
  const reset = root.querySelector("[data-reset]");
  const rows = [...root.querySelectorAll("[data-guess-row]")];
  let vocabulary = [];
  let currentResults = [];
  let visibleCount = 20;

  const loadVocabulary = async () => {
    status.textContent = "Loading the demonstration vocabulary…";
    retry.hidden = true;
    try {
      const response = await fetch(root.dataset.dictionary, {
        headers: { Accept: "application/json" },
      });
      if (!response.ok)
        throw new Error(`Dictionary returned ${response.status}`);
      const payload = await response.json();
      if (payload.schema_version !== 1 || !Array.isArray(payload.candidates)) {
        throw new Error("Unsupported dictionary format");
      }
      vocabulary = payload.candidates.filter(({ word }) =>
        /^[A-Z]{5}$/.test(word),
      );
      status.textContent = payload.coverage_note;
      update();
    } catch {
      status.textContent =
        "The solver vocabulary could not be loaded. Your entries are still here; retry when ready.";
      retry.hidden = false;
    }
  };

  for (const row of rows) {
    const inputs = [...row.querySelectorAll("[data-letter]")];
    const controls = [...row.querySelectorAll("[data-feedback]")];
    inputs.forEach((input, index) => {
      input.addEventListener("input", () => {
        input.value = input.value
          .replace(/[^A-Za-z]/g, "")
          .toUpperCase()
          .slice(0, 1);
        if (input.value && inputs[index + 1]) inputs[index + 1].focus();
        update();
      });
      input.addEventListener("keydown", (event) => {
        if (event.key === "Backspace" && !input.value && inputs[index - 1])
          inputs[index - 1].focus();
      });
      input.addEventListener("paste", (event) => {
        const pasted = event.clipboardData
          ?.getData("text")
          .trim()
          .toUpperCase();
        if (!/^[A-Z]{5}$/.test(pasted ?? "")) return;
        event.preventDefault();
        inputs.forEach((target, letterIndex) => {
          target.value = pasted[letterIndex];
        });
        update();
      });
    });
    controls.forEach((control) => {
      const button = control.querySelector("button");
      const select = control.querySelector("select");
      const setState = (state) => {
        button.dataset.state = state;
        button.textContent = state;
        button.setAttribute("aria-label", `Feedback: ${state}`);
        select.value = state;
        update();
      };
      button.addEventListener("click", () => {
        const index = feedbackStates.indexOf(button.dataset.state);
        setState(feedbackStates[(index + 1) % feedbackStates.length]);
      });
      select.addEventListener("change", () => setState(select.value));
    });
  }

  const readRows = () =>
    rows.map((row) => ({
      guess: [...row.querySelectorAll("[data-letter]")]
        .map((input) => input.value)
        .join(""),
      feedback: [...row.querySelectorAll("[data-feedback] button")].map(
        (button) => button.dataset.state,
      ),
    }));

  const render = () => {
    const visible = currentResults.slice(0, visibleCount);
    results.replaceChildren(
      ...visible.map(({ word }) => {
        const item = document.createElement("li");
        item.textContent = word;
        return item;
      }),
    );
    loadMore.hidden = visibleCount >= currentResults.length;
  };

  function update() {
    if (vocabulary.length === 0) return;
    const enteredRows = readRows();
    const knownWords = new Set(vocabulary.map(({ word }) => word));
    const unknown = enteredRows.find(
      ({ guess }) => guess.length === 5 && !knownWords.has(guess),
    );
    warning.hidden = !unknown;
    if (unknown)
      warning.textContent = `${unknown.guess} is not in this vocabulary, but its feedback still applies.`;
    currentResults = rankCandidates(filterCandidates(vocabulary, enteredRows));
    count.textContent = `${currentResults.length} candidate${currentResults.length === 1 ? "" : "s"}`;
    if (currentResults.length === 0) {
      status.textContent =
        "No candidates match. The feedback may conflict, or the word may be outside this vocabulary.";
    }
    visibleCount = 20;
    render();
  }

  loadMore.addEventListener("click", () => {
    visibleCount += 20;
    render();
  });
  retry.addEventListener("click", loadVocabulary);
  reset.addEventListener("click", () => {
    const hasInput = readRows().some(
      ({ guess, feedback }) =>
        guess.length > 0 || feedback.some((state) => state !== "unset"),
    );
    if (hasInput && !window.confirm("Clear all guesses and feedback?")) return;
    for (const input of root.querySelectorAll("[data-letter]"))
      input.value = "";
    for (const control of root.querySelectorAll("[data-feedback]")) {
      const button = control.querySelector("button");
      button.dataset.state = "unset";
      button.textContent = "Unset";
      button.setAttribute("aria-label", "Feedback: unset");
      control.querySelector("select").value = "unset";
    }
    warning.hidden = true;
    update();
  });

  await loadVocabulary();
}
