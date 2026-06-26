# techno-artisan/phpstan-strict-rules

[![Latest Version on Packagist](https://img.shields.io/packagist/v/techno-artisan/phpstan-strict-rules.svg)](https://packagist.org/packages/techno-artisan/phpstan-strict-rules)
[![Total Downloads](https://img.shields.io/packagist/dt/techno-artisan/phpstan-strict-rules.svg)](https://packagist.org/packages/techno-artisan/phpstan-strict-rules)
[![PHP Version](https://img.shields.io/badge/php-%5E8.5-777bb4.svg)](https://www.php.net/)
[![PHPStan](https://img.shields.io/badge/PHPStan-%5E2-2563eb.svg)](https://phpstan.org/)
[![CI](https://github.com/techno-artisan/phpstan-strict-rules/actions/workflows/ci.yml/badge.svg)](https://github.com/techno-artisan/phpstan-strict-rules/actions/workflows/ci.yml)
[![License](https://img.shields.io/packagist/l/techno-artisan/phpstan-strict-rules.svg)](LICENSE)

> **Uncompromising, purely-syntactic PHPStan rules — because loose semantics hide bugs.**

A set of additional, deliberately strict rules for [PHPStan](https://phpstan.org/).

## Why this package?

- **Purely syntactic.** The rules inspect the AST, not inferred types — so they fire
  predictably on *every* occurrence, with no type-inference guesswork.
- **Uncompromising.** No configuration, no opt-outs: a construct is either allowed or
  it isn't.
- **One thesis.** *Loose semantics hide bugs* — loose `empty()`, loose array searches
  and loose `==` comparisons are all reported.
- **Complements, doesn't replace.** Deliberately stricter than
  [`phpstan/phpstan-strict-rules`](https://github.com/phpstan/phpstan-strict-rules),
  which flags only *provably* type-unsafe comparisons. Use both together.

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
| `DisallowEmptyConstructRule` | The `empty()` language construct | `technoArtisan.disallowedEmpty` |
| `TypedClassConstantRule` | Class constants declared without a native type | `technoArtisan.typedClassConstant` |
| `DisallowLooseInArrayRule` | `in_array()` / `array_search()` / `array_keys()` without `$strict` | `technoArtisan.looseInArray` |
| `DisallowLooseComparisonRule` | The `==`, `!=` and `<>` operators | `technoArtisan.looseComparison` |

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
- PHPStan `^2`

## Development

```bash
composer install   # install dependencies
composer test      # run the PHPUnit suite
composer phpstan   # run PHPStan on src/ — dogfoods this package's own rules
```

## License

MIT — see [LICENSE](LICENSE).
