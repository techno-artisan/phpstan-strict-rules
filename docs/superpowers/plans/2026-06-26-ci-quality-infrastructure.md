# CI Quality Infrastructure (C1 + C2 + C3) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the single CI job with three independent, uncompromising quality gates — a PHPStan lowest/highest test matrix, Infection mutation testing at MSI 100 %, and hygiene gates (100 % line coverage, normalized `composer.json`, NEON lint).

**Architecture:** Three additive, locally-runnable gates wired through new `composer` scripts; two dependency-free `bin/` helper scripts (coverage threshold, NEON lint) that are deliberately kept out of PHPStan's `paths`; a reworked `.github/workflows/ci.yml` with three parallel jobs. The existing local contract (`composer test`, `composer phpstan`) stays green and unchanged.

**Tech Stack:** PHP 8.5, PHPStan ^2, PHPUnit ^12 (pcov coverage), Infection ^0.29, nette/neon ^3, ergebnis/composer-normalize ^2, GitHub Actions.

## Global Constraints

- **PHP floor:** `^8.5`. PHP 8.5 language features are allowed. No PHP version matrix (no spread above 8.5).
- **`declare(strict_types=1);` in every PHP file**, including the new `bin/` scripts. No namespace in the `bin/` scripts.
- **Gates are 100 %, never lowered:** line coverage 100 %, MSI 100 %, covered-MSI 100 %. A failing gate is fixed by improving tests/code, never by weakening the threshold.
- **`bin/` scripts are NOT dogfood-analysed:** `phpstan.neon` keeps `paths: [src]`. Do not add `bin` to `paths`.
- **Local contract unchanged:** `composer test` and `composer phpstan` must still pass on their own, exactly as before. New gates are additive.
- **`composer.json` has no `version` field** — do not add one.
- **`composer.lock` is git-ignored** — never commit it. CI resolves dependencies fresh.
- **Commits:** English, imperative mood, meaningful. Author identity `Techno Artisan <github@technoartisan.dj>` is already set in local git config — plain `git commit` uses it. **No** AI/Claude/Anthropic references, **no** `Co-Authored-By`, **no** "Generated with" lines.
- **Self-review note from spec:** if a gate fails on first run (coverage < 100 %, surviving mutants), that is a *genuine finding* — close the gap, don't relax the gate.

---

## Task Overview & Effort (T-shirt sizes)

| # | Task | Deliverable | Depends on | Size |
| - | ---- | ----------- | ---------- | ---- |
| 1 | Composer foundation | `require-dev` deps, `allow-plugins`, new scripts, normalized `composer.json` | — | **M** |
| 2 | Coverage gate (C3) | `<source>` in `phpunit.xml`, `bin/coverage-gate.php`, 100 % line coverage proven locally | 1 | **M** |
| 3 | NEON lint (C3) | `bin/neon-lint.php` validating `rules.neon` + `phpstan.neon` | 1 | **S** |
| 4 | Infection (C2) | `infection.json5`, MSI + covered-MSI both 100 % (mutants killed) | 1 | **L** |
| 5 | CI workflow (C1+wiring) | `.github/workflows/ci.yml` rewritten into `tests` (lowest/highest matrix), `mutation`, `hygiene` | 1–4 | **M** |
| 6 | Documentation | README commands/positioning, `CHANGELOG.md` Unreleased entry, roadmap doc marked done | 1–5 | **S** |

**Sizing rationale (where it is non-obvious):**
- **Task 1 = M, not S:** the risk is dependency resolution, not editing JSON. `dependency-versions: lowest` lowers *all* requirements; the spec explicitly warns a floor may need bumping if a transitive dev tool's oldest release predates PHP 8.5. Plus first-time plugin allow + getting `normalize` clean.
- **Task 4 = L:** the first Infection run is open-ended. Surviving mutants on the rule library mean strengthening the offending `RuleTestCase`s until MSI hits 100 % — unknown scope, and the whole point of the gate.
- **Task 2 = M:** small script, but real chance the suite is not at 100 % line coverage on first measurement (closing that is genuine test work, per spec).

---

## Task 1: Composer foundation (deps, plugin, scripts, normalize)

**Files:**
- Modify: `composer.json`

**Interfaces:**
- Produces (consumed by later tasks): the `composer` scripts `test:coverage`, `coverage:check`, `infection`, `lint:neon`, `normalize:check`; the dev dependencies `infection/infection`, `nette/neon`, `ergebnis/composer-normalize` available in `vendor/`.

- [ ] **Step 1: Add the three dev dependencies to `require-dev`**

In `composer.json`, replace:

```json
    "require-dev": {
        "phpunit/phpunit": "^12"
    },
```

with:

```json
    "require-dev": {
        "ergebnis/composer-normalize": "^2",
        "infection/infection": "^0.29",
        "nette/neon": "^3",
        "phpunit/phpunit": "^12"
    },
```

- [ ] **Step 2: Allow the composer-normalize plugin**

Replace:

```json
    "config": {
        "sort-packages": true
    },
```

with:

```json
    "config": {
        "allow-plugins": {
            "ergebnis/composer-normalize": true
        },
        "sort-packages": true
    },
```

- [ ] **Step 3: Add the new scripts (keep `test` and `phpstan`)**

Replace:

```json
    "scripts": {
        "test": "phpunit",
        "phpstan": "phpstan analyse"
    },
```

with:

```json
    "scripts": {
        "coverage:check": "@php bin/coverage-gate.php coverage.xml",
        "infection": "infection --min-msi=100 --min-covered-msi=100 --threads=max --no-progress",
        "lint:neon": "@php bin/neon-lint.php",
        "normalize:check": "@composer normalize --dry-run --diff",
        "phpstan": "phpstan analyse",
        "test": "phpunit",
        "test:coverage": "phpunit --coverage-clover=coverage.xml"
    },
```

> The scripts reference `bin/coverage-gate.php` and `bin/neon-lint.php`, which don't exist yet — that is fine. `composer validate` does not check script targets; the scripts simply aren't runnable until Tasks 2 and 3 create those files.

- [ ] **Step 4: Install the new dependencies**

Run: `composer update`
Expected: resolves and installs `infection/infection`, `nette/neon`, `ergebnis/composer-normalize` (plus transitives) against PHP 8.5; no resolver conflict.

> **If resolution fails** because a package's stated floor predates PHP 8.5: raise that package's version constraint in `require-dev` (per the spec's C1 caveat) and re-run — do not weaken anything else.

- [ ] **Step 5: Normalize `composer.json` once so future checks pass**

Run: `composer normalize`
Expected: rewrites `composer.json` into canonical order (the new `require-dev`, `allow-plugins`, and `scripts` entries get normalized). Re-read the file if needed to confirm it still contains all entries from Steps 1–3.

- [ ] **Step 6: Verify the manifest validates and is normalized**

Run: `composer validate --strict`
Expected: `./composer.json is valid` (no errors, no warnings).

Run: `composer normalize:check`
Expected: no diff (exit 0) — confirms Step 5 left nothing unnormalized.

- [ ] **Step 7: Verify the local contract is still green**

Run: `composer test`
Expected: PASS (all existing tests green).

Run: `composer phpstan`
Expected: `[OK] No errors`.

- [ ] **Step 8: Commit**

```bash
git add composer.json
git commit -m "Add dev tooling deps and quality-gate composer scripts"
```

---

## Task 2: Coverage gate (C3)

**Files:**
- Modify: `phpunit.xml`
- Create: `bin/coverage-gate.php`
- Modify: `.gitignore`

**Interfaces:**
- Consumes: `composer test:coverage` (from Task 1) writes a Clover report to `coverage.xml`.
- Produces: `bin/coverage-gate.php <cloverPath>` — reads `/coverage/project/metrics`, prints `Line coverage: NN.NN% (covered/total statements)`, exits `0` iff coverage is exactly 100 % (or there are zero statements), else exits `1`. Consumed by the `tests` CI job via `composer coverage:check`.

- [ ] **Step 1: Scope coverage to production code in `phpunit.xml`**

In `phpunit.xml`, add a `<source>` block directly after the closing `</testsuites>` tag (PHPUnit 12 uses `<source>`, not the legacy `<coverage><include>`):

```xml
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
```

The file then reads:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         failOnWarning="true"
         failOnDeprecation="true"
         cacheDirectory=".phpunit.cache">
    <testsuites>
        <testsuite name="default">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
</phpunit>
```

- [ ] **Step 2: Write the coverage-gate script**

Create `bin/coverage-gate.php`:

```php
<?php

declare(strict_types=1);

$path = $argv[1] ?? 'coverage.xml';

if (!is_file($path) || !is_readable($path)) {
    fwrite(STDERR, "coverage-gate: cannot read Clover file '{$path}'\n");
    exit(1);
}

$xml = simplexml_load_file($path);

if ($xml === false) {
    fwrite(STDERR, "coverage-gate: failed to parse Clover file '{$path}'\n");
    exit(1);
}

$metrics = $xml->xpath('/coverage/project/metrics');

if ($metrics === false || $metrics === []) {
    fwrite(STDERR, "coverage-gate: no /coverage/project/metrics element in '{$path}'\n");
    exit(1);
}

$statements = (int) $metrics[0]['statements'];
$covered = (int) $metrics[0]['coveredstatements'];

$percentage = $statements === 0 ? 100.0 : ($covered / $statements) * 100;

printf("Line coverage: %.2f%% (%d/%d statements)\n", $percentage, $covered, $statements);

if ($percentage < 100.0) {
    $uncovered = $statements - $covered;
    fwrite(STDERR, "coverage-gate: coverage below 100% — {$uncovered} uncovered statement(s)\n");
    exit(1);
}

exit(0);
```

- [ ] **Step 3: Ignore the generated Clover report**

In `.gitignore`, add under the existing entries:

```
/coverage.xml
```

- [ ] **Step 4: Self-check the gate on a deliberately-failing Clover file**

Create a throwaway sub-100 % Clover fixture and confirm the gate rejects it (this is the gate's own test; the file is not committed):

```bash
printf '<?xml version="1.0"?><coverage><project><metrics statements="10" coveredstatements="9"/></project></coverage>' > /tmp/clover-bad.xml
php bin/coverage-gate.php /tmp/clover-bad.xml; echo "exit=$?"
```

Expected: prints `Line coverage: 90.00% (9/10 statements)`, a STDERR message naming `1 uncovered statement(s)`, and `exit=1`.

- [ ] **Step 5: Run real coverage and the gate**

Run: `composer test:coverage`
Expected: tests pass and `coverage.xml` is written.

Run: `composer coverage:check`
Expected: `Line coverage: 100.00% (...)` and exit 0.

> **If coverage is < 100 %:** that is a real gap (per spec). Identify the uncovered lines in `src/` (open the per-file metrics in `coverage.xml`, or run `composer test:coverage` with `--coverage-text`) and add the missing case(s) to the relevant `RuleTestCase` fixture in `tests/Rules/data/` until the gate reports 100.00 %. Do not lower the gate.

- [ ] **Step 6: Confirm the local contract still passes**

Run: `composer phpstan`
Expected: `[OK] No errors` — confirms `bin/coverage-gate.php` stays out of analysis (`paths: [src]` unchanged).

- [ ] **Step 7: Commit**

```bash
git add phpunit.xml bin/coverage-gate.php .gitignore
git commit -m "Add 100% line-coverage gate"
```

---

## Task 3: NEON lint (C3)

**Files:**
- Create: `bin/neon-lint.php`

**Interfaces:**
- Consumes: `Nette\Neon\Neon` from `nette/neon` (installed in Task 1) via `vendor/autoload.php`.
- Produces: `bin/neon-lint.php` (no args) — decodes each of `rules.neon` and `phpstan.neon`; on a `Nette\Neon\Exception` prints `<file>: <message>` to STDERR; exits `1` if any file failed, `0` otherwise. Consumed by the `hygiene` CI job via `composer lint:neon`.

- [ ] **Step 1: Write the NEON-lint script**

Create `bin/neon-lint.php`:

```php
<?php

declare(strict_types=1);

use Nette\Neon\Exception as NeonException;
use Nette\Neon\Neon;

require __DIR__ . '/../vendor/autoload.php';

$files = [
    __DIR__ . '/../rules.neon',
    __DIR__ . '/../phpstan.neon',
];

$failed = false;

foreach ($files as $file) {
    try {
        Neon::decode((string) file_get_contents($file));
    } catch (NeonException $e) {
        fwrite(STDERR, $file . ': ' . $e->getMessage() . "\n");
        $failed = true;
    }
}

exit($failed ? 1 : 0);
```

- [ ] **Step 2: Verify it passes on the real NEON files**

Run: `composer lint:neon`
Expected: no output, exit 0 (both `rules.neon` and `phpstan.neon` parse).

- [ ] **Step 3: Self-check it fails on a corrupted NEON file**

Temporarily break a NEON file, confirm the lint reports it, then restore (do not commit the broken state):

```bash
cp rules.neon /tmp/rules.neon.bak
printf '\n  - : : broken\n' >> rules.neon
php bin/neon-lint.php; echo "exit=$?"
cp /tmp/rules.neon.bak rules.neon
```

Expected: a STDERR line starting with the `rules.neon` path and a parse message, then `exit=1`. After restore, `composer lint:neon` is green again.

- [ ] **Step 4: Confirm the local contract still passes**

Run: `composer phpstan`
Expected: `[OK] No errors` (script stays out of `paths`).

- [ ] **Step 5: Commit**

```bash
git add bin/neon-lint.php
git commit -m "Add NEON lint for rules.neon and phpstan.neon"
```

---

## Task 4: Infection mutation testing (C2)

**Files:**
- Create: `infection.json5`
- Modify: `.gitignore`
- Possibly modify: `tests/Rules/*Test.php` and/or `tests/Rules/data/*` (to kill surviving mutants)

**Interfaces:**
- Consumes: `composer infection` script (from Task 1), pcov coverage produced during the Infection run.
- Produces: a green mutation gate — `composer infection` exits 0 only at MSI 100 % and covered-MSI 100 %.

- [ ] **Step 1: Write the Infection config**

Create `infection.json5`:

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

> The MSI thresholds are **not** in this file — Infection only accepts them as CLI flags. They live in the `composer infection` script (`--min-msi=100 --min-covered-msi=100`), added in Task 1.

- [ ] **Step 2: Ignore the Infection artifacts**

In `.gitignore`, add:

```
/infection.log
/.infection/
```

- [ ] **Step 3: Run the baseline mutation analysis**

Run: `composer infection`
Expected: Infection generates and tests mutants; the run reports MSI and covered-MSI.

> Locally this requires the pcov extension (the CI job sets `coverage: pcov`). If pcov is unavailable locally, the run can fall back to Xdebug coverage; verify the result the same way.

- [ ] **Step 4: Kill every surviving mutant**

If MSI or covered-MSI is below 100 %, open `infection.log`, locate each **escaped** mutant (the file, line, and the mutation applied), and strengthen the responsible rule's `RuleTestCase` so the mutated code would now produce a different analysis result — typically by adding a fixture line in `tests/Rules/data/` and the matching expected error in the test's `analyse([...], [[message, line]])` assertion. Re-run `composer infection` after each change.

Repeat until:

Run: `composer infection`
Expected: `MSI: 100%` and `Covered Code MSI: 100%`, exit 0 (the `--min-msi=100 --min-covered-msi=100` gate passes).

> Per spec: a surviving mutant is fixed by strengthening tests, never by lowering the threshold.

- [ ] **Step 5: Confirm the local contract still passes**

Run: `composer test`
Expected: PASS (any test changes from Step 4 are green).

Run: `composer phpstan`
Expected: `[OK] No errors`.

- [ ] **Step 6: Commit**

```bash
git add infection.json5 .gitignore tests/
git commit -m "Add Infection mutation gate at MSI 100%"
```

> If Step 4 required no test changes, drop `tests/` from the `git add`.

---

## Task 5: CI workflow — three jobs (C1 + gate wiring)

**Files:**
- Modify: `.github/workflows/ci.yml`

**Interfaces:**
- Consumes: every `composer` script from Tasks 1–4 (`phpstan`, `test:coverage`, `coverage:check`, `infection`, `validate`, `normalize:check`, `lint:neon`).
- Produces: three independent CI signals — `tests` (matrixed lowest/highest), `mutation`, `hygiene`.

- [ ] **Step 1: Replace the workflow with three jobs**

Overwrite `.github/workflows/ci.yml` with:

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

- [ ] **Step 2: Lint the workflow YAML locally**

Run: `php -r "echo function_exists('yaml_parse') ? 'yaml-ext' : 'no-yaml-ext', PHP_EOL;"` and, if no YAML extension, validate structurally with:

```bash
composer lint:neon  # sanity: NEON still parses
git diff --stat .github/workflows/ci.yml
```

Primary structural check (every step command was already proven green in Tasks 1–4, so the remaining risk is YAML/matrix correctness):

Run: `php -l` is not applicable to YAML; instead re-read `.github/workflows/ci.yml` and confirm indentation, the `matrix.dependency-versions: [lowest, highest]`, `fail-fast: false`, and that `mutation`/`hygiene` have **no** matrix.

- [ ] **Step 3: Commit**

```bash
git add .github/workflows/ci.yml
git commit -m "Split CI into tests matrix, mutation and hygiene jobs"
```

- [ ] **Step 4: Push and confirm all jobs are green**

```bash
git push
```

Expected on GitHub Actions: four runs (`tests (lowest)`, `tests (highest)`, `mutation`, `hygiene`) all green.

> **If `tests (lowest)` fails** because `dependency-versions: lowest` pulled a transitive dev tool older than PHP 8.5: raise that package's floor in `composer.json` `require-dev` (re-run Task 1 Step 5–6 to re-normalize), commit, and push again. Do not weaken the matrix.

---

## Task 6: Documentation

**Files:**
- Modify: `README.md`
- Modify: `CHANGELOG.md`
- Modify: `docs/ideas/2026-06-25_23-10-06_strict-rules-roadmap-ideen.md`

- [ ] **Step 1: Document the new local commands in the README**

In `README.md`, replace the `## Development` block:

```markdown
## Development

​```bash
composer install   # install dependencies
composer test      # run the PHPUnit suite
composer phpstan   # run PHPStan on src/ — dogfoods this package's own rules
​```
```

with:

```markdown
## Development

​```bash
composer install         # install dependencies
composer test            # run the PHPUnit suite
composer phpstan         # run PHPStan on src/ — dogfoods this package's own rules
composer test:coverage   # run the suite and write coverage.xml
composer coverage:check  # fail unless line coverage is 100%
composer infection       # mutation testing — fail unless MSI is 100%
composer lint:neon       # validate rules.neon and phpstan.neon
composer normalize:check # fail unless composer.json is normalized
​```

This package is strict with itself: CI enforces **100 % line coverage** and a **100 %
mutation score (MSI)**, runs the test suite against both the lowest and highest
supported PHPStan `^2` release, and keeps `composer.json` normalized and the NEON
config lint-clean.
```

> Remove the zero-width-space markers (`​`) shown around the fenced blocks above — they only prevent nested-fence rendering in this plan; the README uses plain ```` ``` ```` fences.

- [ ] **Step 2: Add the `Unreleased` changelog entry**

In `CHANGELOG.md`, replace:

```markdown
## [Unreleased]
```

with:

```markdown
## [Unreleased]

### Added

- **CI quality gates** — the build now runs three independent jobs: a `tests` matrix
  against both the lowest and highest supported PHPStan `^2` release, an Infection
  mutation-testing job gated at **MSI 100 %** and **covered-MSI 100 %**, and a
  `hygiene` job (`composer validate`, `composer normalize` check, NEON lint).
- **100 % line-coverage gate** via `bin/coverage-gate.php` and the new
  `composer test:coverage` / `composer coverage:check` scripts.
- **`composer normalize`** (`ergebnis/composer-normalize`) and a dedicated **NEON lint**
  (`bin/neon-lint.php`, `composer lint:neon`) so a broken config is reported as such
  instead of hiding inside a PHPStan analysis error.
```

- [ ] **Step 3: Mark C1/C2/C3 done in the roadmap doc**

In `docs/ideas/2026-06-25_23-10-06_strict-rules-roadmap-ideen.md`, in section 0 ("Umsetzungsstand"), append to the "Seit der Erstfassung … umgesetzt" list:

```markdown
- **✅ C1 + C2 + C3 — Qualitäts-Infrastruktur** — umgesetzt (Design-Spec
  `docs/superpowers/specs/2026-06-26-ci-quality-infrastructure-design.md`). CI-Matrix
  PHPStan lowest/highest (C1), Infection mit MSI 100 % (C2), 100 % Line-Coverage-Gate +
  `composer normalize` + NEON-Lint (C3). Schließt Lücke #3.
```

Then update the closing summary lines of section 0 so #3 is no longer "offen" — change:

```markdown
**Damit erledigt:** Lücken #1 (loser Vergleich), #2 (manuelle Selbst-Disziplin) und
#4 (dünne Doku). **Noch offen:** #3 (CI-Matrix / Mutation-Tests / Coverage).
```

to:

```markdown
**Damit erledigt:** Lücken #1 (loser Vergleich), #2 (manuelle Selbst-Disziplin),
#3 (CI-Matrix / Mutation-Tests / Coverage) und #4 (dünne Doku).
```

In the comparison matrix (section 4) and the status note beneath it, mark **C1** and **C2** as ✅ umgesetzt (the table currently lists them as open). Change the note:

```markdown
> **Umsetzungsstatus (`v0.1.0-beta.2`):** **A1** ✅ umgesetzt · **B1** ✅ schlank
> umgesetzt (Attribut + README-Codegen zurückgestellt). A2, A4, A5, C1, C2 offen.
```

to:

```markdown
> **Umsetzungsstatus:** **A1** ✅ · **B1** ✅ schlank (Attribut + README-Codegen
> zurückgestellt) · **C1** ✅ · **C2** ✅ · **C3** ✅. Offen: A2, A4, A5, B1-Vollausbau.
```

- [ ] **Step 4: Verify the docs are internally consistent**

Re-read each changed section. Confirm: the README command list matches the actual script names in `composer.json`; the CHANGELOG `[Unreleased]` link at the bottom of the file still resolves; the roadmap no longer lists #3 as open.

Run: `composer lint:neon && composer normalize:check && composer test`
Expected: all green (docs-only change must not break any gate).

- [ ] **Step 5: Commit**

```bash
git add README.md CHANGELOG.md docs/ideas/2026-06-25_23-10-06_strict-rules-roadmap-ideen.md
git commit -m "Document CI quality gates and mark roadmap C1-C3 done"
```

---

## Self-Review (against the spec)

**Spec coverage:**
- `composer.json` require-dev / allow-plugins / scripts → Task 1. ✓
- `phpunit.xml` `<source>` → Task 2 Step 1. ✓
- `infection.json5` → Task 4 Step 1. ✓
- `bin/coverage-gate.php` (argv default, readability exit, XPath `/coverage/project/metrics`, statements===0 ⇒ 100 %, exit codes) → Task 2 Step 2. ✓
- `bin/neon-lint.php` (autoload, fixed file list, `Neon::decode`, STDERR `file: message`, exit) → Task 3 Step 1. ✓
- `.github/workflows/ci.yml` three jobs, lowest/highest matrix, `fail-fast: false`, pcov where needed, hygiene `coverage: none` → Task 5. ✓
- `.gitignore` `/coverage.xml`, `/infection.log`, `/.infection/` → Task 2 Step 3 + Task 4 Step 2. ✓ (`/.phpunit.cache/` already present in `.gitignore`, no change needed.)
- Spec "Tests" (coverage-gate self-check, NEON-lint self-check, Infection baseline) → Task 2 Step 4, Task 3 Step 3, Task 4 Steps 3–4. ✓
- Spec "Documentation" (README, CHANGELOG, roadmap) → Task 6. ✓
- Spec "Verification" (`composer test`/`composer phpstan` stay green; `bin/` out of `paths`; each gate green locally) → contract re-checked in Tasks 1/2/3/4; CI in Task 5. ✓

**Placeholder scan:** No TBD/TODO/"handle edge cases"; every code step shows full code; every run step states expected output. ✓

**Type/name consistency:** Script names (`test:coverage`, `coverage:check`, `infection`, `lint:neon`, `normalize:check`) are identical in Task 1, the CI job (Task 5), and the README (Task 6). `bin/coverage-gate.php` and `bin/neon-lint.php` paths match between Task 1's script definitions and Tasks 2/3's `Create`. ✓

**Out-of-scope (correctly absent):** no PHP version matrix, no consumer smoke-test job, no Codecov/Coveralls upload, no gate below 100 %, no change to the `composer test` / `composer phpstan` contract. ✓
