# techno-artisan/phpstan-strict-rules

Additional strict rules for [PHPStan](https://phpstan.org/).

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

| Rule | Description |
| ---- | ----------- |
| `DisallowEmptyConstructRule` | Reports use of the `empty()` language construct; use an explicit strict comparison instead. |
| `TypedClassConstantRule` | Reports class constants declared without a native type; add an explicit type (e.g. `const int FOO = 1;`). |
| `DisallowLooseInArrayRule` | Reports `in_array()`, `array_search()` and `array_keys()` calls that do not pass `true` as the `$strict` argument; loose comparison hides bugs. |

## License

MIT — see [LICENSE](LICENSE).
