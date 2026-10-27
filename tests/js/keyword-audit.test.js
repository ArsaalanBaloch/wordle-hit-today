import { describe, expect, it } from "vitest";

import {
  classifyKeyword,
  normalizePhrase,
  parseCsv,
} from "../../scripts/keyword-audit.mjs";

describe("keyword CSV parsing", () => {
  it("preserves commas and escaped quotes in quoted fields", () => {
    expect(
      parseCsv(
        'Keyword,KD,US Monthly Search Volume\n"wordle, helper",12,100\n',
      ),
    ).toEqual([
      {
        Keyword: "wordle, helper",
        KD: "12",
        "US Monthly Search Volume": "100",
      },
    ]);
  });

  it("accepts a UTF-8 BOM on the first header", () => {
    expect(
      parseCsv(
        "\uFEFFKeyword,KD,US Monthly Search Volume\nwordle hint,10,100\n",
      ),
    ).toEqual([
      {
        Keyword: "wordle hint",
        KD: "10",
        "US Monthly Search Volume": "100",
      },
    ]);
  });
});

describe("keyword classification", () => {
  it("maps core intents to distinct existing pages", () => {
    expect(classifyKeyword("wordle hint today").target).toBe("/");
    expect(classifyKeyword("wordle solver").target).toBe("/wordle-solver/");
    expect(classifyKeyword("past wordle answers").target).toBe(
      "/wordle-answers/",
    );
    expect(classifyKeyword("how to play wordle").target).toBe(
      "/how-to-play-wordle/",
    );
    expect(classifyKeyword("best wordle starting words").target).toBe(
      "/wordle-strategy/",
    );
  });

  it("excludes competitor, official-navigation and unrelated-game intent", () => {
    expect(classifyKeyword("mashable wordle hint").relevant).toBe(false);
    expect(classifyKeyword("nyt wordle").relevant).toBe(false);
    expect(classifyKeyword("wordle unlimited").relevant).toBe(false);
  });

  it("normalizes punctuation variants for duplicate accounting", () => {
    expect(normalizePhrase("Today's Wordle!")).toBe("today s wordle");
  });
});
