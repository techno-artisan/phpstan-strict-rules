# DisallowLooseInArrayRule Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship a PHPStan rule that reports `in_array()`, `array_search()` and `array_keys()` calls which do not pass the boolean literal `true` as the `$strict` argument.

**Architecture:** A purely **syntactic** PHPStan rule firing on `FuncCall` nodes. It inspects the call AST only (no `Scope`/type inference), matches the three target function names case-insensitively, skips first-class callables and argument unpacking, and reports one error per offending call. Registered under the plain `rules:` shortcut in `rules.neon` (no constructor dependencies), exactly like the two existing rules.

**Tech Stack:** PHP 8.5, PHPStan 2.x (`PHPStan\Rules\Rule`, `RuleErrorBuilder`), nikic/php-parser v5 (`PhpParser\Node\Expr\FuncCall`), PHPUnit 12 (`PHPStan\Testing\RuleTestCase`).

## Global Constraints

- `declare(strict_types=1);` in every PHP file. Copy verbatim.
- Rule classes are `final`.
- Namespace `TechnoArtisan\PhpstanStrictRules\` → `src/`; tests under `TechnoArtisan\PhpstanStrictRules\Tests\` → `tests/`.
- In PHPStan 2.x an `->identifier(...)` is **mandatory** on every error; convention `technoArtisan.<camelCase>`. This rule uses `technoArtisan.looseInArray`.
- Fixtures live in `tests/Rules/data/` (lowercase by design), loaded **by file path**, kept out of PSR-4 autoloading.
- Local contract = CI contract: `composer test` and `composer phpstan` (level `max`, `paths: [src]`) must both pass.
- Commits in **English**, imperative mood, meaningful. Author is the repo's git identity `Techno Artisan <github@technoartisan.dj>` (already set in local git config). **No** AI/Claude/Anthropic references — no `Co-Authored-By`, no "Generated with", no session/tool lines.

---

## File Structure

- **`src/Rules/DisallowLooseInArrayRule.php`** *(create)* — the rule. Single responsibility: detect loose calls to the three array-search functions. No dependencies.
- **`tests/Rules/data/loose-in-array.php`** *(create)* — fixture with the "bad" and "good" calls. Loaded by path; not autoloaded.
- **`tests/Rules/DisallowLooseInArrayRuleTest.php`** *(create)* — `RuleTestCase`: asserts the complete error set and the identifier.
- **`rules.neon`** *(modify)* — register the rule under the existing `rules:` shortcut.
- **`tests/RulesRegistrationTest.php`** *(modify)* — assert the new rule is wired into `rules.neon`.
- **`README.md`** *(modify)* — add the rule to the rules table.

Three tasks: **Task 1** delivers the rule + its `RuleTestCase` + fixture (verified by the rule test in isolation and by dogfooding). **Task 2** wires registration (verified by `RulesRegistrationTest`). **Task 3** documents the rule. A reviewer can meaningfully accept/reject each independently.

---

### Task 1: The rule, its test, and the fixture

**Files:**
- Create: `tests/Rules/data/loose-in-array.php`
- Create: `src/Rules/DisallowLooseInArrayRule.php`
- Test: `tests/Rules/DisallowLooseInArrayRuleTest.php`

**Interfaces:**
- Consumes: nothing — the rule has no constructor dependencies.
- Produces:
  - `final class DisallowLooseInArrayRule implements PHPStan\Rules\Rule` with `getNodeType(): string` returning `PhpParser\Node\Expr\FuncCall::class` and `processNode(PhpParser\Node $node, PHPStan\Analyser\Scope $scope): array` returning `list<PHPStan\Rules\IdentifierRuleError>`.
  - Every emitted error carries identifier `technoArtisan.looseInArray`.
  - Error message: `Call to <fn>() must pass true as the $strict argument; loose comparison coerces types and hides bugs.` where `<fn>` is the lower-cased function name.
  - Task 2 relies on the class name `TechnoArtisan\PhpstanStrictRules\Rules\DisallowLooseInArrayRule`.

- [ ] **Step 1: Write the fixture**

Create `tests/Rules/data/loose-in-array.php` with **exactly** this content. The line numbers of the positive cases (29–36) are asserted by the test in Step 3, so do not reflow the file.

```php
<?php

declare(strict_types=1);

namespace TechnoArtisan\PhpstanStrictRules\Tests\Rules\Data\LooseInArray;

const MY_CONST = true;

final class Haystack
{
    /**
     * A method literally named in_array() — this is a MethodCall node, NOT the
     * global in_array() function, and must therefore never be flagged.
     */
    public function in_array(mixed $needle): bool
    {
        return true;
    }
}

/**
 * @param list<mixed> $list
 * @param array<string, mixed> $arr
 * @param array<int, mixed> $args
 */
function flagged(mixed $x, array $list, array $arr, Haystack $haystack, bool $flag, array $args): void
{
    // --- positive cases: loose array search must be reported ---
    in_array($x, $list);                 // strict missing
    in_array($x, $list, false);          // not true
    in_array($x, $list, $flag);          // not a literal true
    in_array($x, $list, 1);              // not a boolean literal
    in_array($x, $list, MY_CONST);       // not a literal true
    array_search($x, $list);             // strict missing
    array_keys($arr, $x);                // search value present, strict missing
    array_keys($arr, filter_value: $x);  // search value present (named), strict missing

    // --- negative cases: must NOT be reported ---
    in_array($x, $list, true);           // strict true
    in_array($x, $list, strict: true);   // named strict true
    array_search($x, $list, true);       // strict true
    array_keys($arr);                    // no search value, strict irrelevant
    array_keys($arr, $x, true);          // strict true
    $callable = in_array(...);           // first-class callable, not a real call
    in_array(...$args);                  // argument unpacking, positions unknown
    $haystack->in_array($x);             // method named in_array, not the function
}
```

Why these cases map to lines 29–36 (the 8 reported calls): `in_array` (29), `in_array(false)` (30), `in_array($flag)` (31), `in_array(1)` (32), `in_array(MY_CONST)` (33), `array_search` (34), `array_keys($arr, $x)` (35), `array_keys(filter_value:)` (36).

- [ ] **Step 2: Write the skeleton rule so the test can load and fail meaningfully**

A `RuleTestCase` instantiates the rule via `new`, so the class must exist before the test can run and produce a clean assertion failure (rather than a fatal "class not found"). Create `src/Rules/DisallowLooseInArrayRule.php` with a no-op `processNode` for now:

```php
<?php

declare(strict_types=1);

namespace TechnoArtisan\PhpstanStrictRules\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;

/**
 * @implements Rule<FuncCall>
 */
final class DisallowLooseInArrayRule implements Rule
{
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        return [];
    }
}
```

- [ ] **Step 3: Write the failing test**

Create `tests/Rules/DisallowLooseInArrayRuleTest.php`:

```php
<?php

declare(strict_types=1);

namespace TechnoArtisan\PhpstanStrictRules\Tests\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use TechnoArtisan\PhpstanStrictRules\Rules\DisallowLooseInArrayRule;

/**
 * @extends RuleTestCase<DisallowLooseInArrayRule>
 */
final class DisallowLooseInArrayRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new DisallowLooseInArrayRule();
    }

    public function testEveryLooseCallIsReportedAndNothingElse(): void
    {
        // analyse() asserts the COMPLETE error set: the eight loose calls are
        // flagged, and every negative case (strict: true, array_keys($arr), the
        // first-class callable, the ...$args unpacking, the Haystack::in_array()
        // method call) is implicitly asserted to stay clean.
        $this->analyse([__DIR__ . '/data/loose-in-array.php'], [
            [self::message('in_array'), 29],
            [self::message('in_array'), 30],
            [self::message('in_array'), 31],
            [self::message('in_array'), 32],
            [self::message('in_array'), 33],
            [self::message('array_search'), 34],
            [self::message('array_keys'), 35],
            [self::message('array_keys'), 36],
        ]);
    }

    public function testErrorsCarryTheConventionalIdentifier(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/data/loose-in-array.php']);

        self::assertCount(8, $errors);
        foreach ($errors as $error) {
            self::assertSame('technoArtisan.looseInArray', $error->getIdentifier());
        }
    }

    private static function message(string $function): string
    {
        return sprintf(
            'Call to %s() must pass true as the $strict argument; loose comparison coerces types and hides bugs.',
            $function,
        );
    }
}
```

- [ ] **Step 4: Run the test to verify it fails**

Run: `vendor/bin/phpunit --filter DisallowLooseInArrayRuleTest`

Expected: FAIL. `testEveryLooseCallIsReportedAndNothingElse` reports the 8 expected errors were not emitted (the skeleton returns `[]`); `testErrorsCarryTheConventionalIdentifier` fails its `assertCount(8, ...)` (0 errors gathered).

- [ ] **Step 5: Implement the rule**

Replace the entire contents of `src/Rules/DisallowLooseInArrayRule.php` with the full implementation:

```php
<?php

declare(strict_types=1);

namespace TechnoArtisan\PhpstanStrictRules\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Disallows loose comparison in in_array(), array_search() and array_keys():
 * each must pass true as the $strict argument. Loose comparison silently coerces
 * types and hides bugs (e.g. in_array('1', [1, 2, 3]) is true).
 *
 * The rule is intentionally syntactic — it inspects the call AST, not inferred
 * types — so every loose call is reported regardless of the operand types,
 * mirroring DisallowEmptyConstructRule.
 *
 * @implements Rule<FuncCall>
 */
final class DisallowLooseInArrayRule implements Rule
{
    /**
     * Function name (lower-case) => the leading argument index whose presence
     * makes $strict mandatory. in_array/array_search demand it unconditionally
     * (the needle at index 0 is always present); array_keys only when its
     * search value (filter_value at index 1) is present.
     *
     * @var array<string, int>
     */
    private const STRICT_REQUIRED_FROM = [
        'in_array' => 0,
        'array_search' => 0,
        'array_keys' => 1,
    ];

    /** The positional index of the $strict argument across all three functions. */
    private const STRICT_ARG_INDEX = 2;

    /** The named-argument form of array_keys()'s search value. */
    private const SEARCH_VALUE_ARG_NAME = 'filter_value';

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Name) {
            return [];
        }

        $functionName = strtolower($node->name->toString());
        if (!array_key_exists($functionName, self::STRICT_REQUIRED_FROM)) {
            return [];
        }

        // First-class callable in_array(...) — no real call happens.
        if ($node->isFirstClassCallable()) {
            return [];
        }

        $args = $node->getArgs();

        // Argument unpacking (...$args) — positional layout cannot be trusted.
        foreach ($args as $arg) {
            if ($arg->unpack) {
                return [];
            }
        }

        if (!$this->searchValueIsPresent($functionName, $args)) {
            return [];
        }

        if ($this->strictArgumentIsTrue($args)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Call to %s() must pass true as the $strict argument; loose comparison coerces types and hides bugs.',
                $functionName,
            ))
                ->identifier('technoArtisan.looseInArray')
                ->tip('Pass true as the $strict argument to compare strictly.')
                ->build(),
        ];
    }

    /**
     * @param list<Arg> $args
     */
    private function searchValueIsPresent(string $functionName, array $args): bool
    {
        $requiredFrom = self::STRICT_REQUIRED_FROM[$functionName];

        // in_array / array_search: $strict is demanded unconditionally.
        if ($requiredFrom === 0) {
            return true;
        }

        // array_keys: $strict is demanded only when a search value is present,
        // either positionally at index 1 or as the named filter_value argument.
        foreach ($args as $index => $arg) {
            if ($arg->name === null) {
                if ($index >= $requiredFrom) {
                    return true;
                }
            } elseif ($arg->name->toString() === self::SEARCH_VALUE_ARG_NAME) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<Arg> $args
     */
    private function strictArgumentIsTrue(array $args): bool
    {
        foreach ($args as $index => $arg) {
            $isStrictArg = $arg->name === null
                ? $index === self::STRICT_ARG_INDEX
                : $arg->name->toString() === 'strict';

            if ($isStrictArg) {
                return $this->isBooleanTrue($arg->value);
            }
        }

        return false;
    }

    private function isBooleanTrue(Expr $value): bool
    {
        return $value instanceof ConstFetch
            && strtolower($value->name->toString()) === 'true';
    }
}
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `vendor/bin/phpunit --filter DisallowLooseInArrayRuleTest`

Expected: PASS (2 tests, both green).

- [ ] **Step 7: Dogfood the rule against src/**

Run: `composer phpstan`

Expected: PASS — `[OK] No errors`. The new rule file analyses clean at `level: max`. (`paths: [src]`, so the fixture is not analysed.)

- [ ] **Step 8: Run the full suite to confirm nothing regressed**

Run: `composer test`

Expected: PASS — all tests green (the existing `RulesRegistrationTest` still passes; it does not yet reference the new rule).

- [ ] **Step 9: Commit**

```bash
git add src/Rules/DisallowLooseInArrayRule.php tests/Rules/DisallowLooseInArrayRuleTest.php tests/Rules/data/loose-in-array.php
git commit -m "Add DisallowLooseInArrayRule with test and fixture"
```

---

### Task 2: Register the rule

**Files:**
- Modify: `rules.neon`
- Test: `tests/RulesRegistrationTest.php`

**Interfaces:**
- Consumes: `TechnoArtisan\PhpstanStrictRules\Rules\DisallowLooseInArrayRule` from Task 1.
- Produces: the rule is tagged `phpstan.rules.rule` via the `rules:` shortcut, so consumers including `rules.neon` pick it up automatically.

- [ ] **Step 1: Write the failing registration assertion**

In `tests/RulesRegistrationTest.php`, add the import alongside the existing ones (after the `DisallowEmptyConstructRule` import):

```php
use TechnoArtisan\PhpstanStrictRules\Rules\DisallowLooseInArrayRule;
```

and add this assertion at the end of `testEveryRuleIsRegisteredViaRulesNeon`, after the existing `assertContains` calls:

```php
        self::assertContains(DisallowLooseInArrayRule::class, $registered);
```

- [ ] **Step 2: Run the registration test to verify it fails**

Run: `vendor/bin/phpunit --filter RulesRegistrationTest`

Expected: FAIL — `assertContains` reports `DisallowLooseInArrayRule` is not among the registered services (it is not yet in `rules.neon`).

- [ ] **Step 3: Register the rule in `rules.neon`**

Add the third entry under the existing `rules:` shortcut so the file reads:

```neon
rules:
    - TechnoArtisan\PhpstanStrictRules\Rules\DisallowEmptyConstructRule
    - TechnoArtisan\PhpstanStrictRules\Rules\TypedClassConstantRule
    - TechnoArtisan\PhpstanStrictRules\Rules\DisallowLooseInArrayRule
```

- [ ] **Step 4: Run the registration test to verify it passes**

Run: `vendor/bin/phpunit --filter RulesRegistrationTest`

Expected: PASS.

- [ ] **Step 5: Run the full suite and dogfood**

Run: `composer test`
Expected: PASS — entire suite green.

Run: `composer phpstan`
Expected: PASS — `[OK] No errors` (the rule is now active against `src/`, which contains no loose array-search calls, so it stays clean).

- [ ] **Step 6: Commit**

```bash
git add rules.neon tests/RulesRegistrationTest.php
git commit -m "Register DisallowLooseInArrayRule in rules.neon"
```

---

### Task 3: Document the rule

**Files:**
- Modify: `README.md`

**Interfaces:**
- Consumes: nothing in code; documents the behaviour delivered in Tasks 1–2.
- Produces: nothing other tasks depend on.

- [ ] **Step 1: Add the rule to the README rules table**

In `README.md`, add a row to the `## Rules` table, directly after the `TypedClassConstantRule` row, so the table reads:

```markdown
| Rule | Description |
| ---- | ----------- |
| `DisallowEmptyConstructRule` | Reports use of the `empty()` language construct; use an explicit strict comparison instead. |
| `TypedClassConstantRule` | Reports class constants declared without a native type; add an explicit type (e.g. `const int FOO = 1;`). |
| `DisallowLooseInArrayRule` | Reports `in_array()`, `array_search()` and `array_keys()` calls that do not pass `true` as the `$strict` argument; loose comparison hides bugs. |
```

- [ ] **Step 2: Verify the contract commands still pass**

Run: `composer test`
Expected: PASS — entire suite green.

Run: `composer phpstan`
Expected: PASS — `[OK] No errors`.

- [ ] **Step 3: Commit**

```bash
git add README.md
git commit -m "Document DisallowLooseInArrayRule in the README rules table"
```

---

## Self-Review

**Spec coverage** — every spec section maps to a task:

- Scope (three functions, case-insensitive, `\`-qualified, named `strict:`, `array_keys` special case) → Task 1, Step 5 (`STRICT_REQUIRED_FROM`, `strtolower`, `Name::toString()` strips the leading `\`, named-argument handling in `searchValueIsPresent`/`strictArgumentIsTrue`).
- Behaviour table (all 14 rows) → Task 1 fixture (Step 1) + assertions (Step 3): reported rows = lines 29–36; the OK rows and the ignored first-class-callable / unpacking / method-call rows are covered as negative cases asserted clean by `analyse()`.
- Detection algorithm steps 1–8 → `processNode` (name check, map lookup, `isFirstClassCallable`, `unpack` scan, `searchValueIsPresent`, `strictArgumentIsTrue`, `isBooleanTrue`).
- Implementation (final class, `@implements Rule<FuncCall>`, `getNodeType`, internal map, `list<IdentifierRuleError>`, identifier `technoArtisan.looseInArray`, message, `tip`) → Task 1, Step 5.
- Registration (under `rules:`, no `services:`) → Task 2.
- Tests (`RuleTestCase` with the complete-set test and the identifier test; fixture by path; `RulesRegistrationTest` `assertContains`) → Task 1 Step 3 + Task 2 Step 1.
- Documentation (README row) → Task 3.
- Verification (`composer test` + `composer phpstan`) → run at the end of every task.

**Placeholder scan** — no TBD/TODO; every code and command step shows complete content.

**Type consistency** — `getNodeType(): string`, `processNode(Node, Scope): array`/`list<IdentifierRuleError>`, helper signatures `searchValueIsPresent(string, list<Arg>): bool`, `strictArgumentIsTrue(list<Arg>): bool`, `isBooleanTrue(Expr): bool`, constants `STRICT_REQUIRED_FROM` / `STRICT_ARG_INDEX` / `SEARCH_VALUE_ARG_NAME`, identifier `technoArtisan.looseInArray`, and the test helper `message(string): string` are used consistently across the skeleton (Step 2), implementation (Step 5), test (Step 3), and Task 2's `assertContains`.
