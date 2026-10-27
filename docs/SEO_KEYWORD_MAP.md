# SEO keyword map

## Source and method

The supplied `wordle_keywords_kd_volume.csv` contains 1,000 unique rows with keyword difficulty and US monthly search-volume estimates. It has no exact duplicate or zero-volume rows. Search-volume estimates are directional: close variants can describe overlapping searches, so their volumes must not be added and presented as a unique audience total.

Keywords are selected and prioritized by relevance, intent, truthfulness, page fit, coverage gaps and user usefulness. US volume and KD are retained only as source data in the row-level report; neither metric affects selection, exclusion, priority or content placement. A term remains in scope when it matches the product and is excluded when it describes another game, a competitor, an unsupported feature or misleading intent.

## Page assignments

| Destination            | Primary intent      | Representative supplied keywords                                                                  | Content role                                                                              |
| ---------------------- | ------------------- | ------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------- |
| `/`                    | Current daily help  | wordle hint, wordle today, wordle hint today, wordle answer, today's wordle, wordle clue today    | Dated spoiler-controlled hints, answer reveal, local-date explanation and daily FAQ       |
| `/wordle-solver/`      | Candidate filtering | wordle solver, wordle helper, wordle finder, wordle word finder, 5 letter wordle solver           | Functional feedback grid, finder instructions, repeated-letter explanation and solver FAQ |
| `/wordle-answers/`     | Historical lookup   | wordle archive, past wordle words, wordle words used, yesterday's wordle, previous wordle answers | Exact search, dated records, year/month navigation and archive FAQ                        |
| `/wordle-strategy/`    | Improve play        | best wordle starting words, good wordle starting words, wordle start words, wordle tips           | Starting-word principles, second-guess decisions, hard mode and repeats                   |
| `/how-to-play-wordle/` | Learn the rules     | how to play wordle, wordle rules, how does wordle work                                            | Six-guess loop, feedback colours, duplicate allocation and rules FAQ                      |

## Terms retained for monitoring, not forced into content

- Official-site navigational variants such as `nyt wordle`, `new york times wordle` and `play wordle` receive one accurate independent-status explanation and an external link; the site must not imply affiliation or pretend to host the game.
- Competitor-branded searches such as Mashable, Tom's Guide and TechRadar are excluded from editorial targeting.
- Other products and games such as Wordle Unlimited, Connections, Waffle, Quordle and custom Wordle are outside the current product scope.
- Unsupported intents such as creating a Wordle, playing unlimited games, bots, calculators and non-English variants are not placed on pages merely because they have volume or low KD.
- Predictive queries about unused future answers are not targeted because the project publishes validated historical records and does not guess future solutions.

## Anti-stuffing rules

1. Give each intent one primary destination to avoid cannibalization.
2. Use a primary phrase in the title/H1 where natural, then answer related variants semantically rather than repeating exact strings.
3. Add a phrase only when the surrounding section satisfies its intent.
4. Keep competitor names and unrelated games out of titles, headings and repetitive comparison copy.
5. Do not claim a complete archive, official answer list or guaranteed solver coverage until production evidence supports it.
6. Reprioritize using real Search Console impressions, clicks and CTR after a production domain is verified.
