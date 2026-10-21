# Literals finder eval data (this site)

Gold sets and test pools for `drush literals:eval` / `literals:sweep` on this
site's demo library, kept out of the `literals` module because they depend on
this site (hand-made literals such as `balls_email` and `welcome2`, an
`evaltester` user that does not exist yet) and its history. The module ships
only `eval/gold.example.yml` and the method (`eval/TUNING.md`). Results:
[RESULTS.md](RESULTS.md).

| Path | What |
| --- | --- |
| `gold.*.yml` | Gold sets. A set's `requires` names the pools and site literals it needs. |
| `pools/pool100.yml`, `pools/stress.yml` | Test pools (`pl_*`, `st_*`) for `literals:pool`. |
| `pools/scale.yml` | Generator spec for `literals:pool-scale --spec`. |

Drush runs from the docroot, so pass absolute container paths:

```bash
D=/var/www/html/demo/literals-eval
ddev drush literals:eval $D/gold.seed.yml --check          # labels only, no model calls
ddev drush literals:pool load $D/pools/pool100.yml
ddev drush literals:eval $D/gold.seed.yml --dump=/tmp/literals-eval/run1/seed.json
ddev drush literals:sweep /tmp/literals-eval/run1 --pool=pl_ --older=st_
ddev drush literals:pool clean $D/pools/pool100.yml
```

Run `gold.stress.yml` with `pools/stress.yml` loaded too. When a literal is
deleted or a pool added, re-label the sets in the same change (expectations
only, never the question wording) and re-run `--check`.
