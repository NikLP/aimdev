# Demo kit

A fictional community library, used to show AIM (agent memory built in
Drupal) to Drupal developers. Everything here is for this site only, not
part of the `aim` module.

| File | What it does |
| --- | --- |
| `preflight.js` | Thin wrapper: sets the site root and Playwright path, then runs the pre-flight that ships with the recipe. |
| `preflight.local.json` | This site's `publicUrl` and `localUrl`. |
| `literals-eval/` | Gold sets, test pools and sweep results for the literals finder ([README](literals-eval/README.md)). |

The demo itself (31 facts, eight literals, site name, chat persona and
tools, widget wording, recall cutoff), the pre-flight, the reset commands
and the stage flow all live in the `aim_demo_library` recipe:
[its README](../web/modules/custom/aim/recipes/aim_demo_library/README.md).
Change the story there. The ingest test material is in the module's
`tests/ingest-test/`.

Run `node demo/preflight.js` before going on stage.

## The public URL

https://amaria.snake-amberjack.ts.net is a Tailscale Funnel to this laptop,
so the laptop must be awake and online. `tailscale funnel status` shows it;
after a reboot restart it with `tailscale funnel --bg
https+insecure://127.0.0.1:443` (`.ddev/config.yaml` already lists the host
in `additional_fqdns`).

## After a rebuild

Follow the recipe README's "Fresh install", including step 7 (MCP over
OAuth). This site's key path for it is `/var/www/html/keys` (the keys
survive a reinstall). Then set the models and settings below, and run
`node demo/preflight.js`.

## Model settings

The recipe doesn't set any of these; set them by hand after it is applied.
Each is listed as the UI label, then the config key. "Tuned to" names the
model a value was measured on: if you change that model, re-check the
value, because thresholds don't carry over from one model to another.
"Jev" is the TypeSafe AI provider (`typesafeai`) with model
`subq-snap-preview`.

**AI defaults** (`/admin/config/ai/settings`, `ai.settings:default_providers`):
Chat `amazeeio`, Decision Jev, Embeddings `ollama` /
`nomic_embed_text_latest`.

**AIM settings** (`/admin/config/aim/settings`, `aim.settings`):

| Tab > field | Config key | This site | Module default | Tuned to |
| --- | --- | --- | --- | --- |
| Models > Consolidation classifier > Backend, Decision model | `activities.consolidation` | Decision API, Jev | Chat, site default | - |
| Models > Merge verifier > Backend, Decision model | `activities.verifier` | Decision API, Jev | Chat, site default | - |
| Models > Merge verifier > Verifier threshold | `activities.verifier.threshold` | 0.8 | empty (0.5) | Jev |
| Models > Grounded check > Decision model | `activities.grounding` | Jev | none | - |
| Models > Grounded check > Grounded threshold | `activities.grounding.threshold` | 0.7 | empty (0.7) | Jev |
| Models > Grounded check > Mode | `grounding_mode` | Shadow | Off | - |
| Consolidation > Comparison distance | `compare_distance` | 0.45 | 0.45 | `nomic-embed-text` |
| Recall > Recall maximum distance | `recall_max_distance` | 0.60 | 0.48 | `nomic-embed-text` |
| Recall > Recall gap | `recall_gap` | 0 | 0 | `nomic-embed-text` |
| Governance > Trust new facts by default | `default_trusted` | on | off | - |
| Logging & Debug > Log audit events | `log_audit` | on | off | - |
| Logging & Debug > Log verbose detail | `log_verbose` | on | off | - |

The distance values also depend on MariaDB's cosine distance
([ADR-0019](../web/modules/custom/aim/adr/resolved/0019-recall-abstention-distance-cutoff.md)
addendum for 0.52). Recall was loosened to 0.60 with no gap on 2026-10-27:
the calling model judges relevance, and it still said "nothing recorded"
for questions with no answer ([chat-eval](chat-eval/README.md)). The Verifier threshold field only shows
when the fieldset's Backend is Decision API.

**Literals finder** (`/admin/config/literals/finder`,
`literals_finder.settings`): Add the chooser scores to the audit line
(`log_verbose`) on.

**AI logging** (`/admin/config/ai/logging/settings`, `ai_logging.settings`;
site-wide, so neither aim nor the recipe writes it):

| Field | Config key | This site |
| --- | --- | --- |
| Automatically log requests | `prompt_logging` | on |
| Automatically log responses | `prompt_logging_output` | on |
| Restrict automated logging by request tags | `prompt_logging_tags` | `aim_extract,aim_consolidate,aim_consolidate_verify,aim_consolidate_merge,aim_ground,ai_agents_aim_chatbot,literals_choose` |
| Maximum number messages to keep stored in the log | `prompt_logging_max_messages` | 10000 |
| Maximum age of messages to keep stored in the log | `prompt_logging_max_age` | 90 |

**Set by the recipe, for reference**: on the chat block
(`block.block.olivero_aimdemochat`), Gate match threshold
(`settings.plugin_configuration.gate_match_threshold`) 0.8 and Gate choice
margin (`gate_choice_margin`) 0.4. Both were tuned with Jev as the
Decision default, since the finder uses the default Decision model.
