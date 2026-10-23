import { describe, expect, it } from "vitest";

import { completedRowsSignature } from "../../assets/js/solver.js";

describe("solver completed-row signatures", () => {
  it("ignores incomplete guesses and unset feedback", () => {
    const incomplete = [
      {
        guess: "CRAN",
        feedback: ["absent", "absent", "absent", "absent", "absent"],
      },
      {
        guess: "SLATE",
        feedback: ["correct", "present", "unset", "absent", "absent"],
      },
    ];

    expect(completedRowsSignature(incomplete)).toBe("[]");
    expect(
      completedRowsSignature([
        { ...incomplete[0], guess: "CRA" },
        incomplete[1],
      ]),
    ).toBe("[]");
  });

  it("changes only when an effective completed row changes", () => {
    const completed = {
      guess: "CRANE",
      feedback: ["absent", "present", "correct", "absent", "absent"],
    };
    const signature = completedRowsSignature([completed]);

    expect(signature).toBe(
      JSON.stringify([
        ["CRANE", "absent", "present", "correct", "absent", "absent"],
      ]),
    );
    expect(
      completedRowsSignature([
        completed,
        {
          guess: "SLO",
          feedback: ["correct", "correct", "correct", "unset", "unset"],
        },
      ]),
    ).toBe(signature);
    expect(
      completedRowsSignature([
        {
          ...completed,
          feedback: ["correct", "present", "correct", "absent", "absent"],
        },
      ]),
    ).not.toBe(signature);
  });
});
