# Recall eval

`gold.yml` holds 36 questions against the `aim_demo_library` facts, each with
the facts it should return (or none). `run.php` fetches the 30 nearest facts
per question with no cutoff, prints the rank and distance of each expected
fact, then sweeps `recall_max_distance` (with and without `recall_gap`).

Run: `ddev drush php:script demo/recall-eval/run.php`

Columns: **all** = every expected fact within the cutoff, **any** = at least
one, **empty** = a no-answer question returned nothing, **noise** = unexpected
facts returned, summed over all questions.

## 2026-10-27 baseline (`nomic-embed-text`, 31 facts, Claude-drafted set)

The set was written with the fact list open, so it is not blind.

Ranking is mostly right: 29 of 38 expected facts are in the top 3 and 32 in
the top 5. The cutoff is what fails. Right answers range from 0.14 to 0.64,
and the nearest wrong fact for a no-answer question ranges from 0.45 to 0.57,
so no single distance separates them.

| cutoff / gap | all/30 | any/30 | empty/6 | noise |
| --- | --- | --- | --- | --- |
| 0.40 | 17 | 17 | 6 | 20 |
| 0.48 (live that day, module default) | 23 | 24 | 4 | 55 |
| 0.52 | 25 | 26 | 1 | 115 |
| 0.56 | 28 | 30 | 1 | 239 |
| 0.48 / 0.1 | 23 | 24 | 4 | 23 |
| 0.52 / 0.1 (this site's documented setting, restored 2026-10-27) | 24 | 26 | 1 | 46 |

Misses at 0.48 are the broad questions: "any upcoming events?" (all three
expected facts past 0.55; Baby Rhyme Time ranks 11th), "what services do you
offer?" (ranks 6 to 14), "anything happening on Thursday?", "what's on this
week?", plus "is there wheelchair access?" (#5, 0.52) and "how many books can
I take out?" (#4, 0.484). "where can I park?" and "can I return books to
another branch?" return a fact they shouldn't.

Keyword matching would not fix these: none of the missed facts shares a
word with its question ("events" appears in no site fact).
