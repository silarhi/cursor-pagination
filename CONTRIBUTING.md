# Contributing

Contributions are welcome! Bug reports and feature requests go to the
[issue tracker](https://github.com/silarhi/cursor-pagination/issues), changes to a pull request against `main`.

## Setup

```bash
# Install dependencies
composer install
pnpm install # Git hooks: lint-staged formats the staged files on commit
```

The test suite runs on an in-memory SQLite database and requires the `pdo_sqlite` extension: there is no database to
set up.

## Quality checks

The CI runs PHPUnit on every supported PHP version (8.2 to 8.5), with the lowest and the latest dependencies, and
PHPStan, Psalm, PHP CS Fixer and the `composer.json` checks once, on PHP 8.2 with the latest dependencies. Rector does
not run in the CI: run it locally. Make sure your changes pass them all before opening a pull request:

```bash
# Run tests
vendor/bin/phpunit

# Static analysis (level: max)
vendor/bin/phpstan analyse

# Static analysis (Psalm)
vendor/bin/psalm

# Code style check
vendor/bin/php-cs-fixer fix --dry-run --diff

# Code style fix
vendor/bin/php-cs-fixer fix

# Code modernization check
vendor/bin/rector process --dry-run

# composer.json
composer validate --strict
composer normalize --dry-run
```

All at once:

```bash
vendor/bin/phpunit && vendor/bin/phpstan analyse && vendor/bin/psalm && vendor/bin/php-cs-fixer fix --dry-run --diff && vendor/bin/rector process --dry-run
```

## Documentation

The documentation site, [cursor-pagination.silarhi.dev](https://cursor-pagination.silarhi.dev), is built from the
[`docs/`](docs) folder with [Starlight](https://starlight.astro.build). Document a change in the same pull request:

```bash
cd docs
pnpm install
pnpm dev # http://localhost:4321
```

Pull requests touching `docs/` build the site; it is published once merged into `main`.

## License

By contributing, you agree that your contributions are released under the [MIT License](LICENSE).
