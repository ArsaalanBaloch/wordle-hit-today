import { describe, expect, it, vi } from "vitest";

import { dateRelation, localDateParts } from "../../assets/js/main.js";

describe("local date resolution", () => {
  it("builds the date from local calendar parts instead of UTC ISO", () => {
    const resolved = localDateParts(
      new Date("2026-10-16T20:30:00Z"),
      "Asia/Karachi",
    );

    expect(resolved).toEqual({ timezone: "Asia/Karachi", date: "2026-10-17" });
  });

  it("resolves different legitimate calendar dates at one instant", () => {
    const instant = new Date("2026-10-16T11:30:00Z");

    expect(localDateParts(instant, "Pacific/Kiritimati").date).toBe(
      "2026-10-17",
    );
    expect(localDateParts(instant, "America/New_York").date).toBe("2026-10-16");
    expect(localDateParts(instant, "Etc/GMT+12").date).toBe("2026-10-15");
  });

  it("handles leap-day and DST-adjacent calendar formatting", () => {
    expect(
      localDateParts(new Date("2028-02-29T00:30:00Z"), "Asia/Kolkata").date,
    ).toBe("2028-02-29");
    expect(
      localDateParts(new Date("2026-03-08T06:59:00Z"), "America/New_York").date,
    ).toBe("2026-03-08");
  });

  it("reports missing Intl support", () => {
    const spy = vi.spyOn(Intl, "DateTimeFormat").mockImplementation(() => {
      throw new Error("Intl unavailable");
    });

    expect(() => localDateParts()).toThrow("Intl unavailable");
    spy.mockRestore();
  });
});

describe("puzzle date classification", () => {
  it("classifies past, current and later dates without server timezone assumptions", () => {
    expect(dateRelation("2026-10-26", "2026-10-27")).toBe("past");
    expect(dateRelation("2026-10-27", "2026-10-27")).toBe("today");
    expect(dateRelation("2026-10-28", "2026-10-27")).toBe("future");
  });

  it("rejects malformed calendar labels", () => {
    expect(dateRelation("October 27", "2026-10-27")).toBeNull();
  });

  it("covers representative international timezones at a date boundary", () => {
    const instant = new Date("2026-10-26T18:45:00Z");
    expect(localDateParts(instant, "Asia/Karachi").date).toBe("2026-10-26");
    expect(localDateParts(instant, "Asia/Kolkata").date).toBe("2026-10-27");
    expect(localDateParts(instant, "Europe/London").date).toBe("2026-10-26");
    expect(localDateParts(instant, "America/Los_Angeles").date).toBe(
      "2026-10-26",
    );
    expect(localDateParts(instant, "Australia/Sydney").date).toBe("2026-10-27");
  });
});
