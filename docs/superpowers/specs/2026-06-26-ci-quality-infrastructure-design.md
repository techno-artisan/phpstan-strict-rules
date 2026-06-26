# Design: CI quality infrastructure (C1 + C2 + C3)

Status: approved
Date: 2026-06-26

## Motivation

The package ships four purely syntactic PHPStan rules and has frozen its
self-discipline into an executable invariant (`RulesRegistrationTest`). The
remaining open gap from the roadmap (`docs/ideas/2026-06-25_23-10-06_strict-rules-roadmap-ideen.md`,
gap #3) is **quality infrastructure**: the CI currently runs a single job on a
single PHP and a single PHPStan version, with no mutation testing and no
coverage.

For a *rule library*, the credibility of the tests is the product. A rule whose
test stays green while the rule is broken is worse than no rule at all. This spec
closes gap #3 by adding three independent quality gates, each surfaced as its own
CI signal:

- **C1 — PHPStan lowest/highest matrix:** catches drift in the PHPStan rule API
  early, across the range of PHPStan versions consumers actually install.
- **C2 — Infection mutation testing:** proves the rule tests *kill mutants* — the
  most credible quality signal for a rule library.
- **C3 — Hygiene gates:** 100 % line coverage, a normalized `composer.json`, and a
  NEON lint so a broken config is reported as such instead of hiding inside an
  analysis error.

The narrative this reinforces is the package's core value: **a strict-rules
package that is strict with itself.** Every gate is uncompromising (100 %),
matching the rules' own no-exceptions philosophy.

## Scope

In scope:

- A reworked `.github/workflows/ci.yml` with **four parallel jobs**: `tests`,
  `mutation`, `hygiene` (the existing single job is replaced).
- C1: a `dependency-versions: [lowest, highest]` matrix on the `tests` job.
- C2: Infection wired as a `require-dev` dependency, an `infection.json5` config,
  and a `--min-msi=100 --min-covered-msi=100` gate in the `mutation` job.
- C3: line-coverage collection with a **100 %** gate (`bin/coverage-gate.php`),
  `composer normalize --dry-run`, and a dedicated NEON lint (`bin/neon-lint.php`).
- New `composer.json` scripts so every gate is runnable locally, and a `<source>`
  block in `phpunit.xml` for coverage.
- `.gitignore` entries for the new generated artifacts.

Out of scope (YAGNI — explicitly deferred):

- **PHP version matrix** — `composer.json` requires `^8.5`, leaving no version
  spread to test. Gegenstandslos until the floor moves.
- **Consumer smoke-test project** (the C1 "optional" integration job) — deferred;
  the lowest/highest matrix already exercises the public rule API.
- **Third-party coverage upload** (Codecov/Coveralls) — the 100 % gate runs
  entirely in CI; no external service, no token, no extra trust dependency.
- **Lowering the gates below 100 %** — rejected; the package is uncompromising.
- **Changing the local contract** — `composer test` and `composer phpstan` remain
  the two canonical commands; the new gates are additive and independently
  invokable.

## Behaviour

Each gate is an independent green/red signal. The build fails if **any** gate
fails.

| Job | Gate | Fails when |
| --- | ---- | ---------- |
| `tests` (matrix: lowest) | static analysis + tests + 100 % coverage on PHPStan `2.0.x` | analysis error, test failure, or line coverage < 100 % |
| `tests` (matrix: highest) | same, on the newest `^2` PHPStan | same |
| `mutation` | Infection MSI | MSI < 100 % or covered-MSI < 100 % (any surviving mutant) |
| `hygiene` | `composer validate` + `composer normalize` + NEON lint | manifest invalid/unnormalized, or any `.neon` file fails to parse |

The local contract is unchanged: `composer test` and `composer phpstan` still pass
on their own. The new gates add `composer test:coverage`, `composer coverage:check`,
`composer infection`, `composer lint:neon`, and `composer normalize:check`.

## Implementation

### `composer.json`

Add to `require-dev`:

```json
"infection/infection": "^0.29",
"nette/neon": "^3",
"ergebnis/composer-normalize": "^2"
```

> Version floors are chosen to resolve cleanly against PHP 8.5; adjust upward if
> `dependency-versions: lowest` resolution (see C1 below) pulls a version that does
> not support PHP 8.5.

`ergebnis/composer-normalize` is a Composer plugin; allow it under
`config.allow-plugins`:

```json
"config": {
    "sort-packages": true,
    "allow-plugins": {
        "ergebnis/composer-normalize": true
    }
}
```

Add `scripts` (keep the existing `test` and `phpstan`):

```json
"test:coverage": "phpunit --coverage-clover=coverage.xml",
"coverage:check": "@php bin/coverage-gate.php coverage.xml",
"infection": "infection --min-msi=100 --min-covered-msi=100 --threads=max --no-progress",
"lint:neon": "@php bin/neon-lint.php",
"normalize:check": "@composer normalize --dry-run --diff"
```

> After adding `composer-normalize`, run `composer normalize` once so the very
> first `normalize:check` passes — the new `require-dev`, `allow-plugins`, and
> `scripts` entries must themselves be normalized.

### `phpunit.xml`

Add a `<source>` block so coverage is scoped to the production code only (PHPUnit
12 uses `<source>`, not the legacy `<coverage><include>`):

```xml
<source>
    <include>
        <directory>src</directory>
    </include>
</source>
```

No other PHPUnit change; `failOnWarning`/`failOnDeprecation` stay as they are.

### `infection.json5`

```json5
{
    "$schema": "vendor/infection/infection/resources/schema.json",
    "source": {
        "directories": ["src"]
    },
    "mutators": {
        "@default": true
    },
    "logs": {
        "text": "infection.log"
    }
}
```

The MSI thresholds are **not** in the config file (Infection only accepts them as
CLI flags); they live in the `composer infection` script. Infection consumes the
pcov-driven coverage produced during its run.

### `bin/coverage-gate.php`

A small, dependency-free gate (PHPUnit 12 has no native fail-on-threshold). It:

1. Reads the Clover file path from `$argv[1]` (default `coverage.xml`); exits `1`
   with a clear message if missing/unreadable.
2. Loads it with `simplexml_load_file` and reads the project-level aggregate
   metrics via XPath `/coverage/project/metrics` (the summary element, not the
   per-file ones).
3. Compares `statements` vs `coveredstatements`. If `statements === 0` it treats
   coverage as 100 % (no executable lines to cover); otherwise it computes the
   percentage.
4. Prints the percentage and exits `0` at exactly 100 %, else exits `1` naming the
   uncovered statement count.

`declare(strict_types=1);`, no namespace, no autoloader needed (pure SimpleXML).

### `bin/neon-lint.php`

A dedicated NEON validator so a syntax error in `rules.neon`/`phpstan.neon` is
reported as a NEON problem, not buried in a PHPStan analysis failure. It:

1. `require`s `vendor/autoload.php` (to load `Nette\Neon\Neon` — PHPStan's own
   prefixed copy is not directly usable, hence the explicit `nette/neon` dev dep).
2. Iterates a fixed list of the package's NEON files (`rules.neon`,
   `phpstan.neon`).
3. Calls `Nette\Neon\Neon::decode((string) file_get_contents($file))` on each;
   on `Nette\Neon\Exception` prints `file: message` to STDERR and remembers the
   failure.
4. Exits `1` if any file failed, `0` otherwise.

`declare(strict_types=1);`, no namespace.

### `.github/workflows/ci.yml`

Replace the single `tests` job with four jobs. Shared setup: `actions/checkout@v5`,
`shivammathur/setup-php@v2`, `ramsey/composer-install@v4`.

```yaml
name: CI

on:
  push:
    branches: [main]
  pull_request:

permissions:
  contents: read

jobs:
  tests:
    name: Tests & static analysis (PHPStan ${{ matrix.dependency-versions }})
    runs-on: ubuntu-latest
    strategy:
      fail-fast: false
      matrix:
        dependency-versions: [lowest, highest]
    steps:
      - uses: actions/checkout@v5
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: pcov
      - uses: ramsey/composer-install@v4
        with:
          dependency-versions: ${{ matrix.dependency-versions }}
      - run: composer phpstan
      - run: composer test:coverage
      - run: composer coverage:check

  mutation:
    name: Mutation testing (Infection)
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v5
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: pcov
      - uses: ramsey/composer-install@v4
      - run: composer infection

  hygiene:
    name: Hygiene (normalize, NEON lint)
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v5
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.5'
          coverage: none
      - uses: ramsey/composer-install@v4
      - run: composer validate --strict
      - run: composer normalize:check
      - run: composer lint:neon
```

Notes / nuances:

- **C1 caveat:** `dependency-versions: lowest` lowers *all* requirements, not just
  PHPStan. Composer resolves against the CI platform's PHP 8.5, so only
  8.5-compatible versions are chosen *provided each package declares correct PHP
  constraints*. If a `lowest` run fails purely because a transitive dev tool's
  oldest release predates PHP 8.5, raise that package's floor in `require-dev`
  rather than weakening the matrix. The `tests` job installs all dev deps
  (including Infection) but only exercises PHPStan + PHPUnit.
- `fail-fast: false` keeps both matrix legs reporting independently.
- The `mutation` and `hygiene` jobs use `dependency-versions` default (locked/
  highest) — neither is version-sensitive, so they are not matrixed (this is the
  reason Approach C was rejected).

### `.gitignore`

Add the new generated artifacts:

```
/coverage.xml
/infection.log
/.infection/
```

(`/.phpunit.cache/` is already covered by the existing PHPUnit cache handling; add
it here if not.)

## Tests

This change is infrastructure, not a rule, so it adds no `RuleTestCase`. It is
verified by the gates running green and by the gates correctly failing when they
should:

- **Coverage gate self-check:** confirm `bin/coverage-gate.php` exits non-zero on a
  Clover file with `coveredstatements < statements`, and zero at 100 %. The
  existing suite already covers `src/` fully; if the 100 % gate fails on first run,
  that is a real coverage gap to close (a genuine finding, not a gate bug).
- **NEON lint self-check:** confirm `bin/neon-lint.php` exits zero on the current
  `rules.neon`/`phpstan.neon` and non-zero on a deliberately corrupted NEON
  (verified manually, not committed).
- **Infection:** the first `composer infection` run establishes the baseline; any
  surviving mutant at < 100 % MSI is addressed by strengthening the offending
  rule's `RuleTestCase` (the whole point of the gate), not by lowering the
  threshold.

## Documentation

- **`README.md`** — under the existing Development/Requirements notes, document the
  new local commands (`composer test:coverage`, `composer infection`,
  `composer lint:neon`, `composer normalize:check`) and add CI badges for the new
  jobs if badges are kept per-job. Mention the 100 % coverage and 100 % MSI gates
  as part of the "strict with itself" positioning.
- **`CHANGELOG.md`** — add an `Unreleased` → `Changed`/`Added` entry describing the
  CI quality gates (PHPStan lowest/highest matrix, Infection at MSI 100 %, coverage
  gate at 100 %, `composer normalize` + NEON lint).
- **`docs/ideas/2026-06-25_23-10-06_strict-rules-roadmap-ideen.md`** — mark C1, C2,
  and C3 (coverage/normalize/NEON-lint parts) as ✅ done in section 0 and the
  comparison matrix, leaving A2/A4/A5 and the B1 full build as the remaining open
  items.

## Verification

The two local contract commands must still pass on their own:

- `composer test` — unchanged, green.
- `composer phpstan` — unchanged. The two `bin/` scripts are deliberately **not**
  dogfood-analysed: `phpstan.neon` keeps `paths: [src]`, so `bin/` stays out of
  scope. The scripts are tooling, not shipped package code; keeping them out of
  `paths` preserves the "analyse the rules, not the build glue" boundary and avoids
  pulling SimpleXML/`nette` type-resolution noise into the level-max run.

Additionally, each new gate must pass locally before the workflow is trusted:

- `composer test:coverage && composer coverage:check` — reports 100 %.
- `composer infection` — MSI and covered-MSI both 100 %.
- `composer normalize:check` — no diff.
- `composer lint:neon` — both NEON files parse.
