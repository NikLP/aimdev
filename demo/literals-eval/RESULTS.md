# Literals finder sweep results (this site)

Dated `literals:sweep` results on the synthetic demo library (hosted Jev
`subq-snap-preview`). Tuned to that library: not defaults, not for another
site. The method is in the module's
[DEVELOPING.md](../../web/modules/contrib/literals/DEVELOPING.md) "Tuning the thresholds".

## Open

- Judgment-call labels, left as written: "how do I renew a book" (and
  "renew ... online") expects none but gets `login`; "phone number" and
  "call" in the stress set expect a tie but get `main_phone`; two
  agentblind account questions expect a tie but get one literal. Decide
  whether those labels or those answers are wrong.

## Gist overlap (2026-10-22, subq-snap-preview, pool100 and stress seeded)

Seven sets: 191 of 192 answerable questions hit (`nikblind` 20/20); the one
wrong pick (`is the wifi free` got `login`) shares no wording with its gist.
A phrase-overlap warning was built, predicted nothing, and was removed. Reopen
only with a real miss between two similar gists.

## Second sweep (2026-10-21, subq-snap-preview, pool100 seeded)

Commands as in [README.md](README.md): pool100 loaded, all eight sets
dumped into `run1`, the same again into `run2`, `drush literals:sweep
/tmp/literals-eval/run1 --against=/tmp/literals-eval/run2 --pool=pl_
--older=st_` (and again with `--drop-stale`). 312 chooser calls per
run (the five `as: evaltester` rows in `gold.restricted.yml` were skipped:
no such user here; earlier runs asked them silently as anonymous). Replay
check: 312 of 312. Margin 0.2.

| Threshold | Right | Wrong | False pos. | Lost | Suspects set aside: Right | Wrong | False pos. | Lost |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 0.30 | 164 | 15 | 28 | 39 | 164 | 1 | 18 | 2 |
| 0.50 (current) | 164 | 11 | 22 | 43 | 164 | 0 | 12 | 3 |
| 0.55 | 164 | 11 | 19 | 43 | 164 | 0 | 9 | 3 |
| 0.65 | 160 | 9 | 17 | 49 | 160 | 0 | 7 | 7 |
| 0.80 | 148 | 7 | 12 | 63 | 148 | 0 | 2 | 19 |
| 0.90 | 124 | 4 | 7 | 90 | 124 | 0 | 0 | 43 |

Stale-label suspects (61 rows):

- 50 questions expect only a key the asker cannot see, so they are lost or
  wrong at every setting: `opening_hours` x18 (deleted 2026-10-09; only
  `gold.blind.yml` was re-labeled then), `admin_user` x8 and `dimwit` x3
  (no such literal on the site or in any recipe), `my_account` x2
  (restricted, needs `as: 1`), `cfg_phone` x1 (no gist, so never offered),
  and 19 `st_*` (the stress pool was not loaded).
- 14 confident (0.8+) `pl_*` picks where the set expects none or an unseen
  key. Read by hand, 12 are fair answers the pool added (parking, the
  catalogue for book-availability questions x4, loan limit, printing
  price, audiobooks, story time x2, events, complaints). Two are real
  false positives: "loan period at the university library" to
  `pl_loan_period` 0.91 (wrong owner), and arguably "phone number for the
  city council" to `pl_council_phone` 0.92/0.96 (its gist is the council
  desk inside the library). Treat the set-aside columns as a lower bound on errors and
  the raw columns as an upper bound.

Calibration (top literal pick by its probability; suspects set aside in the
last column):

| Top p | Rows | Mean p | Pick right | Suspects set aside |
| --- | --- | --- | --- | --- |
| 0.50-0.60 | 6 | 0.54 | 33% | 2/5 |
| 0.60-0.70 | 12 | 0.65 | 58% | 7/10 |
| 0.70-0.80 | 14 | 0.74 | 64% | 9/13 |
| 0.80-0.90 | 35 | 0.86 | 77% | 27/29 (93%) |
| 0.90-0.95 | 48 | 0.93 | 90% | 43/43 |
| 0.95-0.99 | 78 | 0.97 | 92% | 72/72 |

Stability: of 312 questions in both runs, one changed verdict ("phone #":
`main_phone` 0.47, a miss, then 0.54, a hit) and one changed top pick (on a
row where none won anyway). With the same top pick, the probability moved by
median 0.000, max 0.068. One run is enough on this model; only rows near the
threshold flip.

Reading, against the 2026-10-18 finding:

- **`match_threshold` stays at 0.5.** Not disproved. Right answers are flat
  from 0.30 to 0.55 (164 to 165), so there is a plateau in right answers;
  0.5 is inside it. 0.55 removes three more false positives for nothing
  ("how do I renew a book" to `login` 0.51 in two sets, "call" to
  `main_phone` 0.54), but three rows, two of them one question, on a
  synthetic library is not a reason to move. Above 0.55 each step costs
  right answers again.
- **`choice_margin` is inert here.** 0.00 to 0.25 change nothing; 0.30+
  loses one or two right answers. With a top pick of 0.5 or more, a rival
  within the margin is rare.
- **"The wrong answers are confident ones" is revised.** The confident
  (0.8+) wrong answers and false positives were almost all gold-label
  artifacts. With the suspects set aside there are no wrong answers at 0.4
  and up, picks at 0.9+ are right 115 of 115 times (about 115 of 118 once
  the real false positives among the suspects count), and the remaining false
  positives (12 at 0.5) are mostly mid-confidence wrong-owner or near-topic
  questions ("reset my bank password" to `password_reset` 0.81, "sign in to
  my email" to `login` 0.78, renewing a book to `login`). A threshold can
  reach those, at one or two right answers each above 0.55: a policy choice
  (how much a wrong value costs), not a tuning result.
- Calibration holds: higher confidence means more often right. Below 0.8,
  the hit rate (40% to 70%) runs below the mean probability, so the model is
  somewhat overconfident there. The bins hold few rows.

### After re-labeling (same day)

The 61 suspects were re-labeled (expectations only): deleted keys to none,
`my_account` rows given `as: 1`, `cfg_phone` to none, and the 12 fair
`pl_*` answers accepted alongside none or the `st_*` key. The two real false
positives above keep `none`. Sets now declare `requires` (pools, site
literals), and `drush literals:eval SET --check` reports an expected key no
literal or pool file provides. Re-run with pool100 (312 rows, replay 312/312), margin 0.2:

| Threshold | Right | Wrong | False pos. | Lost |
| --- | --- | --- | --- | --- |
| 0.30 | 179 | 3 | 25 | 16 |
| 0.50 (current) | 179 | 3 | 16 | 16 |
| 0.55 | 178 | 3 | 13 | 17 |
| 0.65 | 174 | 2 | 9 | 22 |
| 0.80 | 161 | 1 | 5 | 36 |
| 0.90 | 136 | 0 | 3 | 62 |

The 3 wrong and 14 of the 16 lost are the stress set run without its pool
(eval now warns about that). Run with `stress` loaded as well, the stress
set scores hit 27/29, wrong 0, false positive 2. Calibration: 0.9+ picks
right 124 of 127 (the three are the real false positives), 0.8-0.9 31 of
34, 0.5-0.8 21 of 34. Against the first run, judged by the same labels:
one verdict changed ("phone #" again). The reading above holds: 0.5 is
inside a flat stretch of right answers (0.30 to 0.50: 179), 0.55 trades
one right answer for three false positives, and what remains is 16 false
positives, mostly wrong-owner and near-topic questions at 0.5 to 0.8.

## First sweep (2026-10-18, subq-snap-preview, 90-literal pool seeded)

Done by hand from `ai_log` (`answers.choice.probabilities` joined to the eval
rows by question text), 311 chooser calls over all eight gold sets, margin
fixed at 0.2. The 2026-10-21 tools replace that method.

Result (threshold: right answers served / wrong answers served / unanswerable
questions answered / right answers lost to none):

| Threshold | Right | Wrong | False pos. | Lost |
| --- | --- | --- | --- | --- |
| 0.50 (current) | 159 | 15 | 19 | 52 |
| 0.65 | 153 | 11 | 16 | 62 |
| 0.80 | 140 | 8 | 12 | 78 |
| 0.90 | 114 | 4 | 8 | 108 |

Reading: no plateau, no good threshold. Each 0.05 step above 0.5 removes
one or two wrong answers and costs about five right ones. The wrong
answers are mostly confident (0.9+, for example "parking nearby" to
`pl_parking`), so a threshold cannot reach them. Correct hits have a median
top probability of 0.94, minimum 0.50. Leave `match_threshold` at 0.5.

Caveat: many "wrong" rows are gold-set artifacts of the big pool (the
`stress` set expects `st_*` literals, and `pl_childrens_phone` is a fair
answer to a question written before the pool existed; several `none`
expectations are now answerable by a `pl_*` literal). Treat the wrong and
false-positive columns as an upper bound and re-label before using them as a
target. What remains is a gist and pool-overlap problem, not a threshold one.
