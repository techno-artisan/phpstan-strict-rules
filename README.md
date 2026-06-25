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

## License

MIT — see [LICENSE](LICENSE).
