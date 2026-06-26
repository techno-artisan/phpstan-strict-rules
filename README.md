# techno-artisan/phpstan-strict-rules

[![Latest Version on Packagist](https://img.shields.io/packagist/v/techno-artisan/phpstan-strict-rules.svg)](https://packagist.org/packages/techno-artisan/phpstan-strict-rules)
[![Total Downloads](https://img.shields.io/packagist/dt/techno-artisan/phpstan-strict-rules.svg)](https://packagist.org/packages/techno-artisan/phpstan-strict-rules)
[![PHP Version](https://img.shields.io/badge/php-%5E8.5-777bb4.svg)](https://www.php.net/)
[![PHPStan](https://img.shields.io/badge/PHPStan-%5E2.2-2563eb.svg)](https://phpstan.org/)
[![CI](https://github.com/techno-artisan/phpstan-strict-rules/actions/workflows/ci.yml/badge.svg)](https://github.com/techno-artisan/phpstan-strict-rules/actions/workflows/ci.yml)
[![Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen.svg)](#quality)
[![Mutation MSI](https://img.shields.io/badge/MSI-100%25-brightgreen.svg)](#quality)
[![License](https://img.shields.io/packagist/l/techno-artisan/phpstan-strict-rules.svg)](LICENSE)

> **Uncompromising, purely-syntactic PHPStan rules — because loose semantics hide bugs.**

A small set of additional, deliberately strict rules for [PHPStan](https://phpstan.org/).
Every line below passes **vanilla PHPStan at `level: max`** — and is reported here:

```php
class Config { const TIMEOUT = 30; }     // ❌ technoArtisan.typedClassConstant
if (empty($user)) { /* … */ }            // ❌ technoArtisan.disallowedEmpty
if ($id == $request->id) { /* … */ }     // ❌ technoArtisan.looseComparison
in_array('1', [1, 2, 3]);                // ❌ technoArtisan.looseInArray  (returns true!)
```

## Why this package?

- **Purely syntactic.** The rules inspect the AST, not inferred types — so they fire
  predictably on *every* occurrence, with no type-inference guesswork.
- **Uncompromising.** No configuration, no opt-outs: a construct is either allowed or
  it isn't.
- **One thesis.** *Loose semantics hide bugs* — loose `empty()`, loose array searches
  and loose `==` comparisons are all reported, alongside untyped class constants.
- **Strict with itself.** Enforced in CI: 100 % line coverage, 100 % mutation score
  (MSI), and the suite runs against both the *lowest* and *highest* supported PHPStan
  `^2.2` release. A rule that isn't bulletproof doesn't ship. See [Quality](#quality).

## Relationship to phpstan/phpstan-strict-rules

[`phpstan/phpstan-strict-rules`](https://github.com/phpstan/phpstan-strict-rules) is
broader and excellent — if you don't already use it, you probably should. Three of the
four rules here overlap with rules it enables by default:

| Construct | phpstan-strict-rules | this package |
| --------- | -------------------- | ------------ |
| `empty()` | bans all (`DisallowedEmptyRule`) | bans all |
| `==` / `!=` / `<>` | bans all (`DisallowedLooseComparisonRule`) | bans all |
| `in_array` / `array_search` / `array_keys` w/o `$strict` | type-aware, plus `base64_decode` (`StrictFunctionCallsRule`) | purely syntactic — requires the literal `true` |
| Untyped class constants | — (no equivalent) | **`TypedClassConstantRule`** |

What this package adds on top:

- **`TypedClassConstantRule`** — strict-rules has no equivalent.
- **A purely syntactic loose-array-search check.** Because it never consults inferred
  types, it also flags `in_array($x, $list, $flag)` when `$flag` is only *inferred* to be
  `true` — predictable, no guesswork. (strict-rules is type-aware and additionally covers
  `base64_decode`.)

The `empty()` and `==` rules are included so this package stands on its own. **If you run
both packages, those two report the same lines twice** — disable them on one side:

```neon
# phpstan.neon — turn off the duplicates in phpstan-strict-rules
parameters:
    strictRules:
        disallowedEmpty: false
        disallowedLooseComparison: false
```

(or ignore the `technoArtisan.*` identifiers from this package instead).

## Installation

```bash
composer require --dev techno-artisan/phpstan-strict-rules
```

## Usage

If you use [`phpstan/extension-installer`](https://github.com/phpstan/extension-installer),
the rules are registered automatically — nothing else to do.

Otherwise, include the rule set manually in your `phpstan.neon`:

```neon
includes:
    - vendor/techno-artisan/phpstan-strict-rules/rules.neon
```

## Rules

| Rule | Reports | Identifier |
| ---- | ------- | ---------- |
| `TypedClassConstantRule` | Class constants declared without a native type | `technoArtisan.typedClassConstant` |
| `DisallowEmptyConstructRule` | The `empty()` language construct | `technoArtisan.disallowedEmpty` |
| `DisallowLooseInArrayRule` | `in_array()` / `array_search()` / `array_keys()` without `$strict` | `technoArtisan.looseInArray` |
| `DisallowLooseComparisonRule` | The `==`, `!=` and `<>` operators | `technoArtisan.looseComparison` |

### Typed class constants — `TypedClassConstantRule`

A class constant without a native type leaves its type implicit. Declare it.

```php
// ❌ reported
final class Config
{
    const TIMEOUT = 30;
}

// ✅ instead
final class Config
{
    const int TIMEOUT = 30;
}
```

Identifier: `technoArtisan.typedClassConstant`

### Loose `empty()` — `DisallowEmptyConstructRule`

`empty()` treats `0`, `0.0`, `"0"`, `""`, `[]`, `null` and `false` alike, so it
silently swallows values you may care about. Use an explicit strict check.

```php
// ❌ reported
if (empty($value)) {}

// ✅ instead
if ($value === null || $value === '') {}
```

Identifier: `technoArtisan.disallowedEmpty`

### Loose array search — `DisallowLooseInArrayRule`

`in_array()`, `array_search()` and `array_keys()` compare loosely unless you pass
`true` as the `$strict` argument, so they coerce types and hide bugs.

```php
// ❌ reported
in_array('1', [1, 2, 3]);          // true — '1' is loosely equal to 1

// ✅ instead
in_array('1', [1, 2, 3], true);    // false
```

Identifier: `technoArtisan.looseInArray`

### Loose comparison — `DisallowLooseComparisonRule`

`==`, `!=` and `<>` coerce their operands' types and hide bugs. Use `===` / `!==`.

```php
// ❌ reported
if ($id == $input) {}   // 0 == 'foo', '1e1' == '10' and null == false are all true

// ✅ instead
if ($id === $input) {}
```

Identifier: `technoArtisan.looseComparison`

## Ignoring a rule

Every error carries a stable identifier (shown above), so you can suppress a single
occurrence with an ignore comment:

```php
$x == $y; // @phpstan-ignore technoArtisan.looseComparison
```

…or ignore it project-wide via `ignoreErrors` in `phpstan.neon` or a generated
baseline. Because the rules are uncompromising by design, prefer fixing the code
over ignoring it.

## Requirements

- PHP `^8.5`
- PHPStan `^2.2`

## Quality

This package is strict with itself. Every release is gated in CI:

- **100 % line coverage** of `src/`, enforced by `composer coverage:check`.
- **100 % mutation score (MSI)** via [Infection](https://infection.github.io/) —
  covered-MSI is 100 % too. A rule whose tests don't kill every mutant doesn't ship.
- **lowest + highest matrix** — the suite runs against both the oldest and newest
  supported PHPStan `^2.2` release, so the rules can't quietly break on either end.
- **Config hygiene** — `composer.json` stays normalized and `rules.neon` / `phpstan.neon`
  are lint-clean.

These same gates run locally — see [Development](#development).

## Development

```bash
composer install         # install dependencies
composer test            # run the PHPUnit suite
composer phpstan         # run PHPStan on src/ — dogfoods this package's own rules
composer test:coverage   # run the suite and write coverage.xml
composer coverage:check  # fail unless line coverage is 100%
composer infection       # mutation testing — fail unless MSI is 100%
composer lint:neon       # validate rules.neon and phpstan.neon
composer normalize:check # fail unless composer.json is normalized
```

## License

MIT — see [LICENSE](LICENSE).
