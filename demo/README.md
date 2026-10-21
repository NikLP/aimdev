# Demo kit (DrupalCon)

A fictional community library, used to show AIM (agent memory built in
Drupal) to Drupal developers. Everything here is for this site only, not
part of the `aim` module.

| File | What it does |
| --- | --- |
| `preflight.js` | Thin wrapper: sets the site root and Playwright path, then runs the pre-flight that ships with the recipe. |
| `preflight.local.json` | This site's `publicUrl` and `localUrl`. |
| `literals-eval/` | Gold sets, test pools and sweep results for the literals finder ([README](literals-eval/README.md)). |

The demo itself (31 facts, three literals, site name, chat persona and
tools, widget wording, recall cutoff), the pre-flight, the reset commands
and the stage flow all live in the `aim_demo_library` recipe:
[its README](../web/modules/custom/aim/recipes/aim_demo_library/README.md).
Change the story there. The ingest test material is in the module's
`tests/ingest-test/`.

Run `node demo/preflight.js` before going on stage.

## The public URL

https://amaria.snake-amberjack.ts.net is a Tailscale Funnel to this laptop,
so the laptop must be awake and online. `tailscale funnel status` shows it.
