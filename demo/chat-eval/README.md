# Chat eval: multi-turn recall modes

`scenarios.yml` holds 10 short conversations against the `aim_demo_library`
facts and literals, for `drush aim:chatbot-converse` (aim_chatbot). Modes,
overridden for the eval process only: `forced` (recall has `require_usage`,
as shipped until 2026-10-22), `free` (`require_usage` off, shipped since), `context` (off, plus
`AimChatbotRecallContextSubscriber` recalling in code each turn and the
persona's "recall first" sentence dropped).

Run: `ddev drush aim:chatbot-converse /var/www/html/demo/chat-eval/scenarios.yml --repeat=2 --dump=/var/www/html/demo/chat-eval/results.json`
(`--modes`, `--only` to narrow).

## 2026-10-27: loose recall cutoff (free mode, 2 runs each, 48 turns)

Six scenarios added for broad and no-answer questions (from
[recall-eval](../recall-eval/README.md)). Run as uid 1, so every scope.

| `recall_max_distance` / `recall_gap` | Turns ok | Mean time per turn |
| --- | --- | --- |
| 0.52 / 0.1 | 45/48 (46 after a judge fix) | 3.2 s |
| 0.60 / 0 | 46/48 | 3.0 s |

No worse with the looser cutoff, and the model still said it had nothing
recorded for parking and a cafe. "Any upcoming events?" failed every run at
both settings: a ranking miss, not a cutoff one. The six events-calendar
project facts rank first, Baby Rhyme Time ranks past the tool's 10-result
limit, and the model gives the book sale and the calendar launch. The other
wrong answer was a judge miss ("There isn't a password", now accepted). Raw
replies: `results-2026-10-27-052.json`, `results-2026-10-27-060.json`.

## 2026-10-22 (amazeeio claude-5-sonnet, 2 runs each, 36 turns)

| Mode | Turns ok | Out of loops | Mean time per turn |
| --- | --- | --- | --- |
| forced | 32/36 | 3 | 6.3 s |
| free | 36/36 | 0 | 3.6 s |
| context | 35/36 | 0 | 2.3 s |

Counts corrected for one judge miss: the Wi-Fi replies said "you don't need
a password" (now accepted by `scenarios.yml`). Failures:

- forced: every phone question ran out of loops (3 of 4), as on the test
  site; one "what number do I call to book it?" rambled without a number.
- context: once gave the main phone instead of the reservations phone for
  booking a room (a literal choice, not recall).

`free` did not reproduce the old Wi-Fi 0/5 (`aim_chatbot` CLAUDE.md): with
this model it recalled every time. Raw replies: `results-2026-10-22.json`.
