# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0-beta.3] - 2026-06-26

### Added

- **CI quality gates** — the build now runs three independent jobs: a `tests` matrix
  against both the lowest and highest supported PHPStan `^2.2` release, an Infection
  mutation-testing job gated at **MSI 100 %** and **covered-MSI 100 %**, and a
  `hygiene` job (`composer validate`, `composer normalize` check, NEON lint).
- **100 % line-coverage gate** via `bin/coverage-gate.php` and the new
  `composer test:coverage` / `composer coverage:check` scripts.
- **`composer normalize`** (`ergebnis/composer-normalize`) and a dedicated **NEON lint**
  (`bin/neon-lint.php`, `composer lint:neon`) so a broken config is reported as such
  instead of hiding inside a PHPStan analysis error.

### Changed

- **Raised the minimum `phpstan/phpstan` to `^2.2`** (from `^2`). The test toolchain
  (PHPUnit 12.5+) pulls `nikic/php-parser ^5.7`, which older PHPStan `2.x` releases
  cannot drive in the coverage analysis path.

This release adds no new rules — the package stays at four rules
(`DisallowEmptyConstructRule`, `TypedClassConstantRule`, `DisallowLooseInArrayRule`,
and `DisallowLooseComparisonRule`) — and focuses on hardening the build with CI quality
gates (mutation testing, full line coverage, NEON lint and config normalization).

## [0.1.0-beta.2] - 2026-06-26

### Added

- **`DisallowLooseComparisonRule`** — reports the loose comparison operators `==`,
  `!=` and `<>` (the last parses to the same node as `!=`), so that loose comparison
  can no longer silently coerce operand types and hide bugs (for example, `0 == 'foo'`,
  `'1e1' == '10'` and `null == false` all evaluate to `true`). The rule is purely
  syntactic and reports every loose comparison regardless of operand types — including
  `== null` — which is deliberately stricter than `phpstan/phpstan-strict-rules`, that
  flags only provably type-unsafe comparisons. Errors use the identifier
  `technoArtisan.looseComparison`.

### Changed

- Overhauled the README: added a "Why this package?" positioning section, status
  badges, and a before/after example plus identifier for every rule, an "Ignoring a
  rule" section, and requirements/development notes.
- Hardened the test suite: a reflection-based guard now enforces that every rule in
  `src/Rules/` is registered in `rules.neon`, is `final`, and declares
  `strict_types=1`, so a rule can no longer be shipped unwired and run silently never
  at consumers.

This release brings the package to four rules: `DisallowEmptyConstructRule`,
`TypedClassConstantRule`, `DisallowLooseInArrayRule`, and `DisallowLooseComparisonRule`.

## [0.1.0-beta.1] - 2026-06-25

### Added

- **`DisallowLooseInArrayRule`** — reports calls to `in_array()`, `array_search()`,
  and `array_keys()` that do not pass `true` as the `$strict` argument, so that loose
  comparison can no longer silently coerce types and hide bugs (for example,
  `in_array('1', [1, 2, 3])` evaluates to `true`). The rule is purely syntactic and
  correctly handles case-insensitive and fully-qualified function names, the named
  `strict:` argument, the `array_keys()` search-value special case (`$strict` is
  required only when a search value is present, positionally or as `filter_value:`),
  first-class callables, argument unpacking, and identically named methods. Errors use
  the identifier `technoArtisan.looseInArray`.

This release brings the package to three rules: `DisallowEmptyConstructRule`,
`TypedClassConstantRule`, and `DisallowLooseInArrayRule`.

## [0.1.0-alpha.3] - 2026-06-25

### Changed

- Documented `TypedClassConstantRule` in the README rules table.
- Documented the project's working conventions and refreshed the environment notes.
- Aligned the repository on a single commit-identity convention.

## [0.1.0-alpha.2] - 2026-06-25

### Added

- Registered and activated **`TypedClassConstantRule`** (shipped since the initial
  release): reports class constants declared without a native type, prompting an
  explicit type such as `const int FOO = 1;`.
- A test suite covering both shipped rules.

## [0.1.0-alpha.1] - 2026-06-25

### Added

- Initial release of `techno-artisan/phpstan-strict-rules` as a PHPStan extension
  (`type: phpstan-extension`), consumable automatically via
  `phpstan/extension-installer` or by including `rules.neon` manually.
- **`DisallowEmptyConstructRule`** — reports use of the `empty()` language construct;
  use an explicit strict comparison instead.
- MIT license.

[Unreleased]: https://github.com/techno-artisan/phpstan-strict-rules/compare/v0.1.0-beta.3...HEAD
[0.1.0-beta.3]: https://github.com/techno-artisan/phpstan-strict-rules/compare/v0.1.0-beta.2...v0.1.0-beta.3
[0.1.0-beta.2]: https://github.com/techno-artisan/phpstan-strict-rules/compare/v0.1.0-beta.1...v0.1.0-beta.2
[0.1.0-beta.1]: https://github.com/techno-artisan/phpstan-strict-rules/compare/v0.1.0-alpha.3...v0.1.0-beta.1
[0.1.0-alpha.3]: https://github.com/techno-artisan/phpstan-strict-rules/compare/v0.1.0-alpha.2...v0.1.0-alpha.3
[0.1.0-alpha.2]: https://github.com/techno-artisan/phpstan-strict-rules/compare/v0.1.0-alpha.1...v0.1.0-alpha.2
[0.1.0-alpha.1]: https://github.com/techno-artisan/phpstan-strict-rules/releases/tag/v0.1.0-alpha.1
