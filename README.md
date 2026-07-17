# PrestaShop Search Evaluation Kit

An open, repeatable checklist for comparing storefront search on a PrestaShop staging shop before changing the production search stack.

Use it for native search, a self-hosted module, or a hosted search service. The kit does not rank vendors, predict conversion, or certify production readiness.

**Maintainer disclosure:** YCY Consulting and Investment SL operates Neuroplugin and sells NP Search. No checklist row depends on NP Search, but the optional demo linked below is our product and is labeled accordingly.

[Versión en español](README.es.md)

## What is included

- `checklist.csv`: 18 observable search, indexing, storefront, operations, and rollback scenarios.
- `SCORING.md`: a deliberately simple evidence model with four hard gates.
- A public issue template for proposing a missing scenario without posting store credentials or customer data.

## How to run it

1. Clone the production configuration into a staging shop. Remove or anonymize customer and order data.
2. Record the exact PrestaShop, PHP, theme, search component, catalogue size, language, currency, and cache configuration.
3. Define a small deterministic fixture: products, combinations, categories, attributes, stock states, accents, SKUs, synonyms, and intentional misspellings.
4. Run the same fixture before and after the proposed search change.
5. Mark each row `pass`, `partial`, `fail`, or `not_applicable`. Link evidence stored in your own controlled system; do not commit private store URLs, credentials, logs, or customer data here.
6. Treat every hard-gate failure as a stop condition until it is resolved and retested.

PrestaShop's current developer documentation recommends testing modules and describes unit, integration, and UI testing. The [PrestaShop 9 search-index documentation](https://devdocs.prestashop-project.org/9/development/components/console/prestashop-search-index/) says a full rebuild can be time-consuming for large catalogues and documents narrower shop and product options. The official front-office guide recommends walking the storefront as a customer and warns that themes and modules can materially change the experience.

Official references:

- [Testing modules — PrestaShop 9](https://devdocs.prestashop-project.org/9/modules/testing/)
- [PrestaShop 9 search index command](https://devdocs.prestashop-project.org/9/development/components/console/prestashop-search-index/)
- [Browsing the front office — PrestaShop 8](https://docs.prestashop-project.org/v.8-documentation/user-guide/browsing-front-office)

## Worked example

The first completed example is a single controlled run against native PrestaShop 8.2.7 search: 15 pass, 1 partial, 0 fail, and 2 not applicable. Its decision is `inconclusive`, because no candidate search change existed to roll back. The partial result records a combination-reference query that opened the default combination rather than the queried one.

[Read the PrestaShop 8.2.7 native-search baseline](examples/prestashop-8.2.7-native/REPORT.md)

## Minimum fixture

Include at least:

- one exact product name and one SKU/reference;
- one accented term and its unaccented form;
- one approved synonym pair;
- one intentional one-character typo;
- one product with combinations;
- one in-stock and one out-of-stock product;
- one hidden or disabled product;
- two categories and at least two filterable attributes;
- one product whose price, stock, name, and category are updated during the test.

## Vendor demo: NP Search only

This link opens our own NP Search 2.13.2 product demo, not a multi-vendor decision tool. The browser-only fixture contains 12 fictional products and lets you change typo recovery, synonyms, stock/category facets, and pin/boost/hide rules, then inspect a simulated decision trace and add-to-cart interaction. It connects to no shop and proves neither latency nor behaviour on your catalogue.

[Open our NP Search-only demo fixture](https://neuroplugin.com/prestashop-search-module?utm_source=github&utm_medium=organic-repo&utm_campaign=search-14d-launch&utm_content=en-evaluation-kit#search-decision-lab)

Always repeat the protocol on the exact shop, theme, catalogue, infrastructure, and search component you are evaluating.

## Disclosure

This kit does not treat any vendor as the default answer. The published native-search example is a baseline, not comparative proof for or against NP Search or any other product.

## License

MIT. See [LICENSE](LICENSE).
