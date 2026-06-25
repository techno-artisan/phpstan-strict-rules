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

PHP 8.5 and Composer are available locally and `vendor/` is installed, so `composer test` and
`composer phpstan` run here and both pass. The package ships two rules
(`DisallowEmptyConstructRule` and `TypedClassConstantRule`), each covered by a `RuleTestCase`.

### Sub-agent model selection (unless the user requests a specific model)
- Trivial → simple tasks: **Haiku**
- Medium → complex tasks: **Sonnet**
- More complex → very complex tasks: **Opus**

### Transparency: skills & agents
- Before a task, briefly state which skills are loaded and why.
- Before starting a sub-agent, state: which agent, which skills it has loaded (confirmation of correctness), and which tools it is allowed to use.
- Before starting a sub-agent, Claude always explains:
  - Which sub-agent (name/role).
  - Which skills the sub-agent has loaded, and asks whether these are correct.
  - Which tools it is allowed to use (if relevant).
    Example:
> 🤖 Sub-agent: `api-developer`  
> 📚 Skills: `api-conventions`, `error-handling-patterns`  
> 🔧 Tools: Read, Write, Bash
## Conventions for working here
- Always ignore `.idea/` (PhpStorm).
- **Commits:** Commit messages in **English** and meaningful (imperative mood). As the author, use exclusively
  the repo's Git identity `Techno Artisan <github@technoartisan.dj>` (set in the local git config). **No** meta-references to AI,
  Claude, or Anthropic — i.e. no `Co-Authored-By`, "Generated with", or session/tool lines.
- New design/spec documents go to `docs/superpowers/specs/`.
