import { describe, expect, it } from "vitest";
import {
  cleanGloss,
  fiveLetterVocabulary,
  parseSenseIndex,
  parseWordNetData,
} from "../../scripts/prepare-production-data.mjs";

describe("production data preparation", () => {
  it("keeps lowercase five-letter ESDB words in reviewed tier order", () => {
    expect(
      fiveLetterVocabulary(
        "apple\nAaron\nASCII\ncrane\n",
        "apple\nslate\nword-\n",
      ),
    ).toEqual(["APPLE", "CRANE", "SLATE"]);
  });

  it("joins WordNet sense order and removes usage examples", () => {
    const senses = parseSenseIndex("apple%1:13:00:: 00001740 1 7\n");
    const rows = parseWordNetData(
      '00001740 13 n 01 apple 00 000 | edible fruit; "she ate an apple"\n',
      "n",
      senses,
    );
    expect(rows).toEqual([
      expect.objectContaining({
        lemma: "APPLE",
        part_of_speech: "noun",
        sense_id: "wn30-n-00001740-00",
        gloss: "edible fruit",
        _sense_number: 1,
        _tag_count: 7,
      }),
    ]);
  });

  it("normalizes definition-only glosses", () => {
    expect(cleanGloss(' a definition ; "an example"; ')).toBe("a definition");
  });

  it("preserves WordNet adjective-satellite sense metadata", () => {
    const senses = parseSenseIndex("abled%5:00:00:: 00002000 2 4\n");
    const rows = parseWordNetData(
      "00002000 00 s 01 abled 00 000 | having a specified ability\n",
      "a",
      senses,
    );

    expect(rows).toEqual([
      expect.objectContaining({
        part_of_speech: "adjective",
        sense_id: "wn30-s-00002000-00",
        _sense_number: 2,
        _tag_count: 4,
      }),
    ]);
  });
});
