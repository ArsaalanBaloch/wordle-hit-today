import { describe, expect, it } from "vitest";

import {
  evaluateFeedback,
  filterCandidates,
  rankCandidates,
} from "../../assets/js/feedback.js";

describe("Wordle feedback", () => {
  it("uses two passes for ALLEY against APPLE", () => {
    expect(evaluateFeedback("APPLE", "ALLEY")).toEqual([
      "correct",
      "present",
      "absent",
      "present",
      "absent",
    ]);
  });

  it("uses two passes for EERIE against LEVEL", () => {
    expect(evaluateFeedback("LEVEL", "EERIE")).toEqual([
      "present",
      "correct",
      "absent",
      "absent",
      "absent",
    ]);
  });

  it("excludes unset rows from filtering", () => {
    const candidates = [
      { word: "APPLE", rank: 1 },
      { word: "LEVEL", rank: 2 },
    ];
    expect(
      filterCandidates(candidates, [
        {
          guess: "CRANE",
          feedback: ["unset", "unset", "unset", "unset", "unset"],
        },
      ]),
    ).toEqual(candidates);
  });
});

describe("candidate ranking", () => {
  it("uses distinct-letter document frequency and deterministic tie-breaks", () => {
    const ranked = rankCandidates([
      { word: "APPLE", rank: 2 },
      { word: "PLEAT", rank: 1 },
      { word: "LEVEL", rank: 3 },
    ]);

    expect(ranked[0].word).toBe("PLEAT");
    expect(ranked[0].score).toBeCloseTo(11 / 3);
    expect(ranked[1].word).toBe("APPLE");
    expect(ranked[1].score).toBeCloseTo(10 / 3);
  });
});
