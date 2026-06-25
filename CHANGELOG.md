# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/techno-artisan/phpstan-strict-rules/compare/v0.1.0-beta.1...HEAD
[0.1.0-beta.1]: https://github.com/techno-artisan/phpstan-strict-rules/compare/v0.1.0-alpha.3...v0.1.0-beta.1
[0.1.0-alpha.3]: https://github.com/techno-artisan/phpstan-strict-rules/compare/v0.1.0-alpha.2...v0.1.0-alpha.3
[0.1.0-alpha.2]: https://github.com/techno-artisan/phpstan-strict-rules/compare/v0.1.0-alpha.1...v0.1.0-alpha.2
[0.1.0-alpha.1]: https://github.com/techno-artisan/phpstan-strict-rules/releases/tag/v0.1.0-alpha.1
