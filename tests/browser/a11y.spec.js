import AxeBuilder from "@axe-core/playwright";
import { expect, test } from "./fixtures.js";

for (const route of [
  "/",
  "/wordle-solver/",
  "/wordle-answers/",
  "/how-to-play-wordle/",
]) {
  test(`has no serious or critical automated accessibility findings: ${route}`, async ({
    page,
  }) => {
    await page.goto(route);
    const results = await new AxeBuilder({ page }).analyze();
    const serious = results.violations.filter(({ impact }) =>
      ["serious", "critical"].includes(impact),
    );

    expect(serious).toEqual([]);
  });
}
