import { describe, expect, it } from "vitest";

import {
  archivePartitionsForQuery,
  archiveQueryType,
  parseArchiveManifest,
  parseArchivePartition,
} from "../../assets/js/archive.js";

describe("archive manifest validation", () => {
  it("accepts only year-matched same-origin index paths", () => {
    const indexes = parseArchiveManifest({
      schema_version: 1,
      archive_indexes: {
        2025: "/assets/data/archive-2025.123456abcdef.json",
        2026: "/assets/data/archive-2026.abcdef123456.json",
      },
    });

    expect([...indexes.entries()]).toEqual([
      ["2025", "/assets/data/archive-2025.123456abcdef.json"],
      ["2026", "/assets/data/archive-2026.abcdef123456.json"],
    ]);
    expect(() =>
      parseArchiveManifest({
        schema_version: 1,
        archive_indexes: {
          2026: "https://attacker.invalid/archive-2026.abcdef123456.json",
        },
      }),
    ).toThrow("unsafe index path");
    expect(() =>
      parseArchiveManifest({
        schema_version: 1,
        archive_indexes: {
          2026: "/assets/data/archive-2025.abcdef123456.json",
        },
      }),
    ).toThrow("unsafe index path");
  });

  it("selects only the matching year for date searches", () => {
    const indexes = new Map([
      ["2025", "/assets/data/archive-2025.123456abcdef.json"],
      ["2026", "/assets/data/archive-2026.abcdef123456.json"],
    ]);

    expect(archivePartitionsForQuery(indexes, "date", "2026-10-16")).toEqual([
      ["2026", "/assets/data/archive-2026.abcdef123456.json"],
    ]);
    expect(archivePartitionsForQuery(indexes, "word", "CRANE")).toEqual([
      ["2025", "/assets/data/archive-2025.123456abcdef.json"],
      ["2026", "/assets/data/archive-2026.abcdef123456.json"],
    ]);
  });
});

describe("archive entry validation", () => {
  it("returns sanitized entries with canonical dated paths", () => {
    expect(
      parseArchivePartition(
        [
          {
            date: "2026-10-16",
            number: 1580,
            word: "CRANE",
            url: "/wordle-answers/2026-10-16/",
            ignored: "not copied",
          },
        ],
        "2026",
      ),
    ).toEqual([
      {
        date: "2026-10-16",
        number: 1580,
        word: "CRANE",
        url: "/wordle-answers/2026-10-16/",
      },
    ]);
  });

  it("rejects invalid dates, cross-year entries and noncanonical paths", () => {
    const valid = {
      date: "2026-10-16",
      number: 1580,
      word: "CRANE",
      url: "/wordle-answers/2026-10-16/",
    };

    expect(() =>
      parseArchivePartition(
        [{ ...valid, date: "2026-02-30", url: "/wordle-answers/2026-02-30/" }],
        "2026",
      ),
    ).toThrow("invalid entry");
    expect(() => parseArchivePartition([valid], "2025")).toThrow(
      "invalid entry",
    );
    expect(() =>
      parseArchivePartition(
        [
          {
            ...valid,
            url: "https://attacker.invalid/wordle-answers/2026-10-16/",
          },
        ],
        "2026",
      ),
    ).toThrow("invalid entry");
  });
});

describe("archive query validation", () => {
  it("rejects impossible dates and unsafe numeric values", () => {
    expect(archiveQueryType("2026-10-16")).toBe("date");
    expect(archiveQueryType("2026-02-30")).toBeNull();
    expect(archiveQueryType("1580")).toBe("number");
    expect(archiveQueryType("99999999999999999")).toBeNull();
    expect(archiveQueryType("crane")).toBe("word");
  });
});
