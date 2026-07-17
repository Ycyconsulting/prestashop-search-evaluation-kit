# PrestaShop 8.2.7 native-search baseline

This is one controlled run of the checklist against native PrestaShop search. It is not a vendor comparison, a performance benchmark, a production-readiness certificate, or evidence of conversion impact.

**Maintainer disclosure:** YCY Consulting and Investment SL operates Neuroplugin and sells NP Search. NP Search was not installed or used in this run.

## Decision record

- Run window: 2026-07-17 22:10:11–22:18:15 UTC, beginning with fixture seeding.
- Outcomes: 15 `pass`, 1 `partial`, 0 `fail`, 2 `not_applicable`.
- Hard gates: visibility/data boundary `pass`; index correctness `pass`; storefront continuity `pass`; rollback `not_applicable`.
- Decision: `inconclusive`.
- Reason: this was a native-search baseline, not a before/after candidate evaluation, so no candidate rollback was available to exercise.
- Important limitation: an exact combination reference returned the correct base product but linked to the default combination rather than the queried combination.

## Environment

| Item | Observed value |
| --- | --- |
| Host boundary | Disposable Docker Compose project; front office bound to `127.0.0.1:18080`; no production data or credentials |
| Architecture | `arm64` |
| PrestaShop | 8.2.7 |
| PHP | 8.1.33 |
| PrestaShop image | `prestashop/prestashop:8.2.7` at `sha256:e4fb5b37f3f3acb013c6c8716f99b6164b0f749e1e0f3a3963a458f5bcbba51d` |
| Database | MariaDB 10.11.18; `mariadb:10.11` at `sha256:be981e4113326ada8d6004174dd09eeaefc03094037f811182a52d4f2e737350` |
| Theme | `classic` |
| Catalogue | 26 products after seeding: 19 installer products plus 7 synthetic products; 25 active and indexed |
| Languages | English only |
| Currencies | EUR at 1.0 and USD at 1.144545 during the run |
| Search modules | `ps_searchbar` 2.1.4; `ps_facetedsearch` 4.0.4 |
| Native search settings relevant here | Indexation on; fuzzy search on; minimum word length 3; product-name weight 6; reference weight 10 |

The shop domain was fixed to `localhost:18080` and dynamic-domain handling was disabled. The fixture ran as the Apache user so application cache ownership remained valid.

## Synthetic fixture

| Reference | Product/state |
| --- | --- |
| `NPBENCH-TRAIL-001` | Benchmark Trail Trainers; EUR 79; quantity 10; Benchmark Footwear and Benchmark Outdoor |
| `NPBENCH-AUDIO-001` | Benchmark Studio Headphones; EUR 129; quantity 5; Benchmark Audio |
| `NPBENCH-MUG-001` | Benchmark Café Mug; EUR 12.50; quantity 20; Benchmark Accessories |
| `NPBENCH-BAG-001` | Benchmark Canvas Bag; EUR 35; quantity 8; Benchmark Accessories and Benchmark Outdoor; mutation target |
| `NPBENCH-TEE-BASE` | Benchmark Combination Tee; EUR 24; four size/color combinations; Benchmark Accessories |
| `NPBENCH-LAMP-001` | Benchmark Out of Stock Lamp; EUR 44; quantity 0; Benchmark Accessories |
| `NPBENCH-HIDDEN-001` | Benchmark Hidden Telescope; inactive, visibility `none`, and Benchmark Outdoor |

The tee combinations were Small/Blue (`NPBENCH-TEE-S`) quantity 4, Small/Red (`NPBENCH-TEE-S-RED`) quantity 3, Large/Blue (`NPBENCH-TEE-L-BLUE`) quantity 2, and Large/Red (`NPBENCH-TEE-L`) quantity 0. The only configured search alias mapped `sneakers` to `trainers`.

CLI probes exercised the deterministic search and index states; browser-observed steps exercised result pages, zero-result messaging, facets, product options, currency switching, and cart continuity. The test harness is retained with the worked-example source, but the run was not a manual stopwatch benchmark.

## Hard-gate evidence

### Visibility and data boundary

The hidden telescope returned no result. The observed search, facet, product, currency, and cart workflow produced only local shop requests in the Apache access log, including local `/search`, category XHR, product, `/cart`, and shopping-cart module endpoints. A transport-keyword review of core `Search.php`, `ps_searchbar.php`, and `ps_facetedsearch.php` found no runtime outbound HTTP call in those files. The rendered pages contained ordinary external links, including the PrestaShop footer and product share links, but they were not followed. No customer, checkout, or order data was entered.

This is an observation of the exercised native-search workflow, not an audit of every installed module or every possible storefront action.

### Index correctness

The mutation changed the bag to **Benchmark Updated Travel Satchel**, EUR 39, quantity 6, and moved it to Benchmark Audio. Before incremental reindexing, the new term had no result. After the documented product reindex path, `updated travel satchel` returned exactly the changed product with the new price, stock, category URL, and no duplicate.

The old query `canvas` no longer returned the changed product. With native fuzzy search enabled it did return two unrelated fixture products, so the evidence is target membership rather than a claim that the old query became an empty result. The Benchmark Accessories category page no longer contained the changed product; the Benchmark Audio category page and database association did.

A full rebuild completed in 58 ms on this 26-product fixture. The PHP process moved from 14,680,064 bytes to 16,777,216 bytes with a 16,777,216-byte observed peak. Container snapshots were approximately 241 MiB for the application and 205 MiB for MariaDB before and after. These figures describe only this tiny local catalogue.

For failure recovery, the headphones search index was safely removed. The exact query changed from one result to zero, then returned to one after a product reindex completed in 16 ms.

### Storefront continuity

The exact Trail Trainers result displayed EUR 79 on the result page, product page, add-to-cart confirmation, and cart. The Small/Blue tee filter result preserved both attributes and EUR 24 through the product page and add-to-cart confirmation.

After switching the same cart to USD, Trail Trainers displayed USD 90.42 and the Small/Blue tee USD 27.47; the two-item tax-inclusive cart total was USD 117.89. The out-of-stock lamp remained searchable with an `Out-of-Stock` label, while the Large/Red tee option disabled Add to cart.

### Rollback

No candidate search component or configuration was installed, so restoring a prior search stack was outside this baseline. This is `not_applicable`, not a pass, and is why the decision remains `inconclusive`.

## Scenario evidence

<a id="s01"></a>
### S01 — Exact product name: pass

`Benchmark Trail Trainers` returned only `NPBENCH-TRAIL-001` in English.

<a id="s02"></a>
### S02 — SKU or reference: partial

The product reference `NPBENCH-TRAIL-001` was unambiguous. The combination reference `NPBENCH-TEE-L` returned the correct tee, but the result URL selected the default Small/Blue combination rather than the queried Large/Red combination. The base product was correct; the combination was not unambiguous.

<a id="s03"></a>
### S03 — Accent handling: pass

Both `café` and `cafe` returned only Benchmark Café Mug.

<a id="s04"></a>
### S04 — Approved synonym: pass

Both `trainers` and configured alias `sneakers` returned only Trail Trainers.

<a id="s05"></a>
### S05 — One-character typo: pass

The unconfigured one-character substitution `trainerz` returned only Trail Trainers with native fuzzy search enabled; the correct `trainers` query returned the same product.

<a id="s06"></a>
### S06 — Zero-result recovery: pass

`purple telescope` returned no products and the front office displayed “No matches were found for your search” with a new search field.

<a id="s07"></a>
### S07 — Disabled or hidden product: pass

The inactive, visibility-`none` telescope did not appear by exact name before or after a full rebuild.

<a id="s08"></a>
### S08 — Stock state: pass

The in-stock trainer and zero-quantity lamp were searchable; the lamp displayed `Out-of-Stock`. The zero-quantity Large/Red tee disabled Add to cart.

<a id="s09"></a>
### S09 — Price and currency: pass

EUR and USD prices remained consistent from result/product context into the cart for the exercised products and combinations.

<a id="s10"></a>
### S10 — Facets and categories: pass

Before the S14 mutation, the Benchmark Accessories category contained the mug, bag, tee, and lamp. Applying Small and Blue showed both active filters, internally consistent counts of one, and only the Small/Blue tee. S14 subsequently moved the bag to Benchmark Audio.

<a id="s11"></a>
### S11 — Combination selection: pass

The Small/Blue filtered result URL retained both attributes. Product page and cart confirmation both showed Blue, Small, quantity 1, and the expected price.

<a id="s12"></a>
### S12 — Result-to-cart continuity: pass

Trail Trainers preserved product identity, quantity 1, EUR 79 tax-inclusive price, stock availability, and currency from exact search to cart.

<a id="s13"></a>
### S13 — Locale isolation: not_applicable

Only English was active. No second storefront language existed to exercise.

<a id="s14"></a>
### S14 — Incremental catalogue change: pass

Name, price, stock, and category changed together. A product reindex completed in 47 ms; the changed product left the old unique-term result, appeared once for the new term, and moved category pages without a stale copy.

<a id="s15"></a>
### S15 — Full rebuild: pass

The full rebuild completed in 58 ms with the small-fixture memory observations recorded above. Exact, changed, and hidden-product probes remained correct afterward.

<a id="s16"></a>
### S16 — Failure and recovery: pass

A safe product-index deletion made the exact headphones query return zero. Product reindex restored the one expected result in 16 ms.

<a id="s17"></a>
### S17 — External services: pass

All observed search, filter, product, currency, and cart requests stayed on the local shop. No checkout/order fields were used, and no native-search transport to an external service was identified in the reviewed path. Scope limitations are stated in the hard-gate section.

<a id="s18"></a>
### S18 — Restore prior search: not_applicable

There was no candidate search change to roll back. A future before/after evaluation must exercise this hard gate before any `ship` decision.

## Reproduce the local fixture

This directory includes the exact `compose.yml` and `fixture.php` used for the final run. Supply unique disposable values for the three required environment variables, start the Compose project, then copy the fixture into the PrestaShop container and run it as `www-data`:

```sh
NP_BASELINE_DB_PASSWORD=<unique-db-password> \
NP_BASELINE_DB_ROOT_PASSWORD=<unique-root-password> \
NP_BASELINE_ADMIN_PASSWORD=<unique-admin-password> \
docker compose up -d

docker cp fixture.php neuroplugin-ps8-native-baseline-prestashop-1:/tmp/np-search-baseline-fixture.php
docker exec --user www-data neuroplugin-ps8-native-baseline-prestashop-1 \
  php /tmp/np-search-baseline-fixture.php seed
```

The fixture also exposes `probe <query>`, `mutate`, `reindex-product`, `full-reindex`, `clear-index`, and `restore-index`. The published Compose file pins the project name used by the example container command. Treat generated catalogue IDs as run-specific; the `NPBENCH-` references are stable.

## Reproduction cautions

- Use a staging or disposable shop and synthetic data only.
- Run application maintenance commands as the web user when they clear PrestaShop cache files.
- Keep the host/domain fixed in local containers; the dynamic-domain image helper can otherwise create a same-URL redirect at `/`.
- Recreate the fixture and rerun every hard gate on the exact production-like theme, modules, languages, currencies, cache, catalogue scale, and infrastructure.
- Do not generalize the timing, memory, fuzzy-search behaviour, or combination-reference limitation without repeating the test on the target shop.
