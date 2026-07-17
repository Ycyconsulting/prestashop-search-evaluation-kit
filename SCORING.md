# Evidence and decision model

This kit favors inspectable evidence over a composite score that can hide a critical failure.

## Row outcomes

- `pass`: the expected observation was reproduced and evidence was retained.
- `partial`: some paths passed, but a defined variant, locale, device, or state did not.
- `fail`: the expected observation was not reproduced or a regression was introduced.
- `not_applicable`: the scenario is outside the documented scope; record why.

Do not convert `not_applicable` into a pass. Do not compare two systems unless the fixture, environment, cache state, and observation method are the same.

## Hard gates

A candidate must not move to production while any of these gates fails:

1. **Visibility and data boundary:** disabled/private products are not exposed, and external data flows are documented.
2. **Index correctness:** catalogue changes converge to the expected searchable state through a documented rebuild or incremental path.
3. **Storefront continuity:** the result-to-product-to-cart path preserves the correct product, combination, price, stock rule, locale, and currency.
4. **Rollback:** the team can restore the prior search behavior and verify it with the same fixture.

## Decision record

Record:

- exact environment and fixture revision;
- hard-gate result;
- counts of `pass`, `partial`, `fail`, and `not_applicable`;
- unresolved failures and owner;
- the decision: `ship`, `iterate`, `stop`, or `inconclusive`;
- the date and evidence location.

The result demonstrates only what the fixture observed. It does not prove causal revenue impact, universal compatibility, legal compliance, or performance at an untested catalogue and traffic scale.
