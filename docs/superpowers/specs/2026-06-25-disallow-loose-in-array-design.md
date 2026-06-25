# Design: `DisallowLooseInArrayRule`

Status: approved
Date: 2026-06-25

## Motivation

`in_array()`, `array_search()` and `array_keys()` default to **loose** comparison.
This silently coerces types and hides real bugs:

```php
in_array('1', [1, 2, 3]);   // true  — '1' == 1
in_array(0, ['a', 'b']);    // depends on PHP version / coercion surprises
```

The package already ships `DisallowEmptyConstructRule` with exactly this philosophy
— *"loose semantics hide bugs"*. This rule continues that narrative for the three
array search functions, requiring callers to opt into strict comparison via
`strict: true`.

The rule is intentionally **syntactic** (it inspects the call AST, not inferred
types). Its behaviour is therefore uncompromising and predictable, mirroring
`DisallowEmptyConstructRule`: every loose call is reported regardless of operand
types. No `Scope`/type inference is consulted.

## Scope

In scope:

- Report `in_array`, `array_search`, `array_keys` calls whose `strict` argument is
  not the boolean literal `true`.
- Function-name matching is case-insensitive and matches both the unqualified name
  (`in_array`) and the fully qualified form (`\in_array`).
- Support the `strict:` **named argument** as well as the positional third argument.
- Honour the `array_keys` special case: `strict` is only required when a search
  value is supplied.

Out of scope (YAGNI):

- Type-aware ("only when operand types actually clash") reporting — explicitly
  rejected in favour of the uncompromising behaviour.
- End-user configurability of the function list — the function set lives as an
  internal `const` map; the rule is registered under the plain `rules:` shortcut,
  consistent with the two existing rules.
- User-defined functions that happen to be named `in_array` inside a namespace
  (extremely rare; not worth the type-resolution cost for a syntactic rule).

## Behaviour

One error is produced **per offending call** (not per argument).

| Call | Result |
| ---- | ------ |
| `in_array($x, $list)` | reported — `strict` missing |
| `in_array($x, $list, false)` | reported — not `true` |
| `in_array($x, $list, $flag)` | reported — not a literal `true` |
| `in_array($x, $list, 1)` | reported — not a boolean literal |
| `in_array($x, $list, MY_CONST)` | reported — not a literal `true` |
| `in_array($x, $list, true)` | ok |
| `in_array($x, $list, strict: true)` | ok — named argument recognised |
| `array_search($x, $list)` | reported — same logic as `in_array` |
| `array_search($x, $list, true)` | ok |
| `array_keys($arr)` | ok — no search value, `strict` irrelevant |
| `array_keys($arr, $x)` | reported — search value present, `strict` missing |
| `array_keys($arr, $x, true)` | ok |
| `array_keys($arr, filter_value: $x)` | reported — search value present (named) |
| `in_array(...)` (first-class callable) | ignored — not an actual call |
| any call using argument unpacking `...$args` | ignored — argument positions not reliably known |

### Detection algorithm (per `FuncCall` node)

1. Resolve the called function name. If `$node->name` is not a `Name`
   (dynamic call such as `$fn(...)`), ignore.
2. Lower-case the resolved name. If it is not one of the three target functions,
   ignore.
3. If any argument is a `VariadicPlaceholder` (first-class callable syntax
   `foo(...)`), ignore — no real call happens.
4. If any argument uses spread/unpacking (`Arg->unpack === true`), ignore — the
   positional layout cannot be trusted, so reporting would risk false positives.
5. Determine the per-function "search-value threshold" from the internal map:
   - `in_array`, `array_search`: `strict` is always expected.
   - `array_keys`: `strict` is only expected when a search value is present
     (a positional argument at index 1, or a named argument `filter_value`).
   If the threshold is not met, ignore.
6. Locate the `strict` argument: the positional argument at index 2, or a named
   argument whose name is `strict`.
7. If that argument exists and its value is a boolean-literal `true`
   (`PhpParser\Node\Expr\ConstFetch` whose lower-cased name is `true`), the call
   is OK.
8. Otherwise, report one error.

## Implementation

File: `src/Rules/DisallowLooseInArrayRule.php`

- `final class DisallowLooseInArrayRule implements Rule`, `declare(strict_types=1)`,
  `@implements Rule<FuncCall>`.
- `getNodeType(): string` returns `PhpParser\Node\Expr\FuncCall::class`.
- Internal map encoding the per-function semantics at a single declarative place,
  e.g.:

  ```php
  // function name => number of preceding arguments after which $strict is required
  private const STRICT_REQUIRED_FROM = [
      'in_array'     => 0,
      'array_search' => 0,
      'array_keys'   => 1,
  ];
  ```

  The value is the minimum count of "leading" arguments (search value etc.) that
  must be present before `strict` is demanded. `array_keys` requires the search
  value (1) to be present; the other two demand `strict` unconditionally (0).
- `processNode()` implements the algorithm above and returns
  `list<IdentifierRuleError>`.
- Error identifier: `technoArtisan.looseInArray` (camelCase convention).
- Error message names the function, e.g.:
  `Call to in_array() must pass true as the $strict argument; loose comparison coerces types and hides bugs.`
- `->tip('Pass true as the $strict argument to compare strictly.')` for actionable
  guidance.

## Registration

`rules.neon` — add the third entry under the existing `rules:` shortcut:

```neon
rules:
    - TechnoArtisan\PhpstanStrictRules\Rules\DisallowEmptyConstructRule
    - TechnoArtisan\PhpstanStrictRules\Rules\TypedClassConstantRule
    - TechnoArtisan\PhpstanStrictRules\Rules\DisallowLooseInArrayRule
```

No `services:` entry and no constructor dependencies — consistent with the two
existing rules.

## Tests

`tests/Rules/DisallowLooseInArrayRuleTest.php` extending `RuleTestCase`:

- `getRule()` returns `new DisallowLooseInArrayRule()`.
- A test that calls `analyse()` against the fixture and asserts the **complete**
  set of expected errors. Because `analyse()` asserts the full error set, every
  negative case (named `strict: true`, `array_keys($arr)`, the first-class
  callable, the unpacking call, the method named `in_array`) is implicitly
  asserted to stay clean.
- A second test gathering analyser errors and asserting every error carries the
  `technoArtisan.looseInArray` identifier (same style as the existing tests).

Fixture `tests/Rules/data/loose-in-array.php` (loaded by file path, kept out of
PSR-4 autoloading). Contains, with explanatory comments, at least:

- Positive: `in_array`/`array_search` without third arg, with `false`, with a
  variable, with `1`, with a constant; `array_keys` with a search value but no
  `strict`; `array_keys` with named `filter_value` but no `strict`.
- Negative: each function with `strict`/third arg `true`; `in_array` with named
  `strict: true`; `array_keys($arr)` with no search value; the first-class
  callable `in_array(...)`; a call using `...$args` unpacking; a method call on an
  object whose method is named `inArray`/`in_array` (must not be confused with the
  global function).

`tests/RulesRegistrationTest.php`: add
`self::assertContains(DisallowLooseInArrayRule::class, $registered);`.

## Documentation

`README.md` rules table — add:

| Rule | Description |
| ---- | ----------- |
| `DisallowLooseInArrayRule` | Reports `in_array()`, `array_search()` and `array_keys()` calls that do not pass `true` as the `$strict` argument; loose comparison hides bugs. |

## Verification

Both local contract commands must pass:

- `composer test` — the new `RuleTestCase` and the updated registration test pass.
- `composer phpstan` — the new rule file analyses clean at `level: max`
  (dogfooding).
