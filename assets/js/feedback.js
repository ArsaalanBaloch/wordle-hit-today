export const feedbackStates = ["unset", "absent", "present", "correct"];

export function evaluateFeedback(answer, guess) {
  const normalizedAnswer = normalizeWord(answer);
  const normalizedGuess = normalizeWord(guess);
  const result = Array(5).fill("absent");
  const remaining = new Map();

  for (let index = 0; index < 5; index += 1) {
    if (normalizedAnswer[index] === normalizedGuess[index]) {
      result[index] = "correct";
    } else {
      const letter = normalizedAnswer[index];
      remaining.set(letter, (remaining.get(letter) ?? 0) + 1);
    }
  }

  for (let index = 0; index < 5; index += 1) {
    if (result[index] === "correct") continue;
    const letter = normalizedGuess[index];
    if ((remaining.get(letter) ?? 0) > 0) {
      result[index] = "present";
      remaining.set(letter, remaining.get(letter) - 1);
    }
  }

  return result;
}

export function filterCandidates(candidates, rows) {
  const completedRows = rows.filter(
    ({ guess, feedback }) =>
      /^[A-Z]{5}$/.test(guess) &&
      feedback.length === 5 &&
      feedback.every((state) => state !== "unset"),
  );

  return candidates.filter(({ word }) =>
    completedRows.every(
      ({ guess, feedback }) =>
        evaluateFeedback(word, guess).join(",") === feedback.join(","),
    ),
  );
}

export function rankCandidates(candidates) {
  if (candidates.length === 0) return [];
  const frequencies = new Map();
  for (const { word } of candidates) {
    for (const letter of new Set(word)) {
      frequencies.set(letter, (frequencies.get(letter) ?? 0) + 1);
    }
  }

  return candidates
    .map((candidate) => ({
      ...candidate,
      score:
        [...new Set(candidate.word)].reduce(
          (total, letter) => total + frequencies.get(letter),
          0,
        ) / candidates.length,
    }))
    .sort(
      (left, right) =>
        right.score - left.score ||
        (left.rank ?? Number.MAX_SAFE_INTEGER) -
          (right.rank ?? Number.MAX_SAFE_INTEGER) ||
        left.word.localeCompare(right.word, "en"),
    );
}

function normalizeWord(word) {
  const normalized = word.toUpperCase();
  if (!/^[A-Z]{5}$/.test(normalized)) {
    throw new TypeError("Words must contain exactly five ASCII letters.");
  }
  return normalized;
}
