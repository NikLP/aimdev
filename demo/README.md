# Demo kit (DrupalCon)

A fictional community library, used to show AIM (agent memory built in
Drupal) to Drupal developers. Everything here is for this site only, not
part of the `aim` module.

| File | What it does |
| --- | --- |
| `preflight.js` | End-to-end check of everything the demo needs, about 35 seconds. Cleans up after itself. |
| `preflight.config.json` | The questions and expected answers the pre-flight uses. |
| `ingest-test/` | Test material for an ingest run (TESTS.md, type B), not part of the demo. |

The demo itself (31 facts, three literals, site name, chat persona and
tools, widget wording, recall cutoff) is the `aim_demo_library` recipe in
the module: [its README](../web/modules/custom/aim/recipes/aim_demo_library/README.md).
Change the story there.

## Before going on stage

```bash
node demo/preflight.js
```

It checks the site (local and through the public funnel URL), Ollama, the
vector index, the full OAuth handshake as Claude.ai does it (client
registration, consent, PKCE), all four scopes over MCP, MCP abstention on an
off-topic question, save-then-search, and the chat widget in a real browser
(answer, then write-then-read), plus that anonymous visitors can't see or
call the widget. It needs DDEV up, Tailscale Funnel on,
Ollama running, and Playwright (set `PLAYWRIGHT_PATH` if it is not at the
path in the script). Any FAIL line says what to check.

## Reset between runs

Facts added during a run stay until you reset. The chat widget is for
logged-in users only (anonymous visitors don't see it and `/api/deepchat`
returns 403), so the audience can't add facts through it. Return to the
starting state by deleting every fact and re-applying the recipe:

```bash
ddev drush entity:delete aim_fact -y
ddev drush queue:delete aim_consolidate
ddev drush recipe:apply /var/www/html/web/modules/custom/aim/recipes/aim_demo_library
ddev drush search-api:index aim_vector_index
```

Re-applying skips literals that already exist, so a literal edited on
stage keeps its edit; put it back by hand.

## The public URL

https://amaria.snake-amberjack.ts.net is a Tailscale Funnel to this laptop,
so the laptop must be awake and online. `tailscale funnel status` shows it.

## Suggested flow (about 8 minutes)

1. **The widget, site scope.** Log in first (`ddev drush uli
   --uri=https://amaria.snake-amberjack.ts.net`), since the widget is
   hidden from anonymous visitors. Open the chat and ask:
   - "When are you open on Sundays?" (recalls from memory)
   - "Do you charge late fees?" (finds "overdue fines": meaning, not keywords)
   - "Can I bring my dog?"
   - "Do you have a cafe?" (it says it doesn't have that recorded)
   - "We now have a cafe, open 10 to 3 every day." Then ask about the cafe
     again. It saved the fact and recalls it immediately.
   - "What is the capital of Australia?" (it stays on topic)
2. **Under the hood.** Log in (`ddev drush uli --uri=https://amaria.snake-amberjack.ts.net`)
   and open `/admin/content/aim-facts`: facts are content entities, with
   scope (a bundle), source, provenance and the person who wrote them.
   `/admin/config/aim/settings` has the recall cutoff, and
   `/admin/config/aim/user-scope-access` the role-visibility matrix.
3. **The same memory from another agent, over MCP.** In Claude.ai add a
   custom connector for `https://amaria.snake-amberjack.ts.net/mcp`
   (OAuth, dynamic client registration, scopes `aim:recall aim:remember`).
   Then ask Claude:
   - "What must editors do before publishing an image?" (role scope)
   - "How does Sam like answers formatted?" (user scope, your own account)
   - "When does the events calendar launch?" (case scope)
   - Tell Claude a new fact, then ask the widget about it.
4. **Optional, from the terminal.**
   - `ddev drush aim:recall "opening hours" --scope=site --max-distance=0.48`
   - Save a near-duplicate ("The library opens at 9am on weekdays"), then
     `ddev drush aim:consolidate --scope=site --dry-run` to show the
     merge/retire decision.

## Exact values (literals), about 3 minutes

The recipe imports three literals (`main_phone`, public; `staff_line` and
`my_account`, restricted: signed-in visitors only, through `view restricted
literals`). It also gives the assistant aim's lookup tool
(`tool:aim_literal`), and its persona says to use it for phone numbers and
web addresses.

1. **Live value.** In the widget ask "How do I ring the library?" The
   assistant calls the lookup tool and gives the number exactly. Edit
   `main_phone` at `/admin/content/literals` and ask again: the new number,
   no reindex. A reset does not overwrite an edited literal; put it back
   by hand.
2. **Different viewer.** The staff line is visible only when logged in. As
   anonymous it is not found; no fact names it.
3. **Another agent.** Over MCP, ask Claude for the library phone number: the
   `aim_literal` tool returns the same value, as the OAuth user.

Pre-flight covers the lookup tool and the restricted-literal boundary
(`literals:` check). Keep demo literals one exact value each ("the main phone number"),
not topics like opening hours, which vary by day.

## What to say about how it is built

- Facts are content entities. The four scopes (user, role, site, case) are
  bundles, real `aim_scope` config entities.
- Search is Search API with the AI Search backend and MariaDB's native
  `VECTOR` type with an HNSW index, in the same database as everything
  else. Embeddings run locally on Ollama.
- Every write goes through `drupal/ai` Guardrails. Consolidation
  (dedupe/supersede) runs from the Queue API, not on the request.
- The MCP tools are Tool API plugins, exposed with `mcp_server`, secured
  with `simple_oauth` scopes and Drupal permissions per scope.
- Recall drops matches past a calibrated distance, so an unrelated
  question gets "nothing relevant" instead of the nearest unrelated fact.

## Be upfront about

- It is a proof of concept. There is no draft-to-trusted review step yet:
  every fact is live the moment it is saved (ADR-0002 designs it).
- The chat widget needs the `access deepchat api` permission, which only
  authenticated users have (registration is admin-only). Anyone you give an
  account can add facts, so reset after a session.
- Chat replies take about 6 seconds (a hosted model plus a memory lookup);
  MCP recall and the search itself take milliseconds.
- The recall cutoff is a heuristic, calibrated per dataset. A question near
  a topic the facts don't cover can still get the nearest facts, and the
  model has to notice they don't answer it.
- Do not use it for classified data: a hosted model can see what it recalls.

## What the recipe changes

Site name and slogan, the chat agent's and assistant's instructions and
tools, the widget label, welcome message and signed-in-only visibility,
`access deepchat api` for authenticated users, `index_directly`, and
`aim.settings:recall_max_distance` (0.48; the module default is 0.45, see
ADR-0019's second calibration). It no longer sets a front page.
