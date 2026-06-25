# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A public Composer package (`techno-artisan/phpstan-strict-rules`, `type: phpstan-extension`)
that ships additional **strict rules** for [PHPStan](https://phpstan.org/). It is a PHPStan
extension consumed by other projects, not an application.

## Commands

```bash
composer install                       # install dependencies (creates vendor/, needed before anything else)
composer test                          # run the full PHPUnit suite (alias for vendor/bin/phpunit)
vendor/bin/phpunit --filter testEmptyConstructIsReported   # run a single test
composer phpstan                       # run PHPStan on src/ — dogfoods this package's own rules
```

## Architecture

The whole package is a registration pipeline from a rule class to a consumer's PHPStan run:

- **`rules.neon`** is the single registration entry point. `composer.json` →
  `extra.phpstan.includes` points here, so consumers using `phpstan/extension-installer`
  pick up the rules automatically; others include `rules.neon` manually.
- **A rule** is a class in `src/Rules/` implementing `PHPStan\Rules\Rule`:
  - `getNodeType()` returns the `PhpParser\Node` subclass the rule fires on
    (e.g. `Empty_::class`).
  - `processNode()` returns a list of errors built with `RuleErrorBuilder`. In PHPStan 2.x
    an `->identifier(...)` is **mandatory** (convention: `technoArtisan.<camelCase>`).
  - Rules **without constructor dependencies** are listed under the `rules:` shortcut in
    `rules.neon`. Rules that **need dependencies** (type resolvers, reflection, etc.) must
    instead be registered under `services:` with the `phpstan.rules.rule` tag — the `rules:`
    shortcut cannot inject arguments.
- **Tests** extend `PHPStan\Testing\RuleTestCase` (shipped inside `phpstan/phpstan`, no extra
  dependency). `getRule()` returns the rule under test; `analyse([fixture], [[message, line]])`
  asserts the expected errors.
- **Fixtures** live in `tests/Rules/data/` and are loaded **by file path**, not via autoload.
  They deliberately contain the "bad" code a rule should flag, so they must stay out of PSR-4
  autoloading (the `data/` directory is lowercase by design and PHPUnit ignores non-`*Test.php`
  files).

### Adding a rule

1. Create the rule class in `src/Rules/`.
2. Register it in `rules.neon`.
3. Add a `RuleTestCase` in `tests/Rules/` plus a fixture in `tests/Rules/data/`.
4. Document it in the README rules table.

## Conventions

- `declare(strict_types=1);` in every PHP file; rule classes are `final`.
- Namespace `TechnoArtisan\PhpstanStrictRules\` → `src/`; tests under `...\Tests\` → `tests/`.
- Targets **PHP 8.5** (`composer.json` requires `^8.5`); 8.5 language features are allowed.

## CI

`.github/workflows/ci.yml` (GitHub Actions) runs `composer phpstan` and `composer test` on
PHP 8.5 for every push to `main` and every pull request. The same two commands are the local
contract — if they pass locally they pass in CI.

## Note

PHP/Composer are not installed in the current environment, so the scaffold (one example rule
`DisallowEmptyConstructRule` + its test) has **not** been executed here. Run `composer install`
before relying on `composer test` / `composer phpstan`.
