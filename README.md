<p align="center">
    <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/packagist/v/silarhi/cursor-pagination?style=for-the-badge&label=stable&color=0d6efd&labelColor=1a1a2e">
        <img src="https://img.shields.io/packagist/v/silarhi/cursor-pagination?style=for-the-badge&label=stable&color=0d6efd"
            alt="Latest Stable Version">
    </picture>
    <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/packagist/dt/silarhi/cursor-pagination?style=for-the-badge&color=198754&labelColor=1a1a2e">
        <img src="https://img.shields.io/packagist/dt/silarhi/cursor-pagination?style=for-the-badge&color=198754" alt="Total Downloads">
    </picture>
    <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/packagist/l/silarhi/cursor-pagination?style=for-the-badge&color=6f42c1&labelColor=1a1a2e">
        <img src="https://img.shields.io/packagist/l/silarhi/cursor-pagination?style=for-the-badge&color=6f42c1" alt="License">
    </picture>
    <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/packagist/php-v/silarhi/cursor-pagination?style=for-the-badge&color=777bb4&labelColor=1a1a2e">
        <img src="https://img.shields.io/packagist/php-v/silarhi/cursor-pagination?style=for-the-badge&color=777bb4" alt="PHP Version">
    </picture>
    <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/github/actions/workflow/status/silarhi/cursor-pagination/continuous-integration.yml?style=for-the-badge&label=CI&color=20c997&labelColor=1a1a2e">
        <img src="https://img.shields.io/github/actions/workflow/status/silarhi/cursor-pagination/continuous-integration.yml?style=for-the-badge&label=CI&color=20c997"
            alt="CI Status">
    </picture>
    <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fsilarhi%2Fcursor-pagination%2Fbadges%2Fcoverage.json&style=for-the-badge&labelColor=1a1a2e">
        <img src="https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fsilarhi%2Fcursor-pagination%2Fbadges%2Fcoverage.json&style=for-the-badge" alt="Coverage">
    </picture>
</p>

<h1 align="center">Cursor Pagination</h1>

<p align="center">
    <strong>Cursor-based pagination for Doctrine ORM QueryBuilders.</strong><br>
    Walk through large tables in fixed-size batches, without the cost of growing <code>OFFSET</code>s.
</p>

---

With `OFFSET` pagination, the database still reads and discards every skipped row, so each page gets slower as you go
deeper, and rows inserted or deleted during the run shift the pages, so some are skipped or processed twice. Cursor
(keyset) pagination starts each page after the last row seen (`u.createdAt > :createdAt OR (u.createdAt = :createdAt
AND u.id > :id)`), so every page costs the same on an indexed ordering and concurrent writes do not move the cursor.

## Features

- **Keyset pagination** — each page is fetched with a `WHERE` clause built from the last row of the previous page.
- **Multi-field ordering** — combine several fields, each ascending or descending, ending with a unique one.
- **Item or chunk iteration** — iterate entity by entity, or by arrays of `maxPerPages` items for flush/clear batches.
- **Restartable iterations** — every iteration starts from the beginning, even if a previous one was stopped early.
- **Counting** — `count()` and `getNbPages()` rely on Doctrine's `Paginator`.

## Requirements

| Dependency   | Version |
| ------------ | ------- |
| PHP          | 8.2+    |
| doctrine/orm | ^3.0    |

## Installation

```bash
composer require silarhi/cursor-pagination
```

## Usage

```php
use Silarhi\CursorPagination\Configuration\OrderConfiguration;
use Silarhi\CursorPagination\Configuration\OrderConfigurations;
use Silarhi\CursorPagination\Pagination\CursorPagination;

$queryBuilder = $entityManager
    ->getRepository(User::class)
    ->createQueryBuilder('u')
    ->where('u.enabled = true');

$configurations = new OrderConfigurations(
    new OrderConfiguration('u.createdAt', static fn (User $user): \DateTimeImmutable => $user->getCreatedAt()),
    new OrderConfiguration('u.id', static fn (User $user): int => $user->getId()),
);

/** @var CursorPagination<User> $pagination */
$pagination = new CursorPagination($queryBuilder, $configurations, 100);

// Method 1: get results as chunks (recommended)
foreach ($pagination->getChunkResults() as $users) {
    foreach ($users as $user) {
        $user->setEnabled(false);
    }

    $entityManager->flush();
    $entityManager->clear();
}

// Method 2: get results one by one ($pagination is also iterable directly)
foreach ($pagination->getResults() as $user) {
    // do something with $user
}

$pagination->count();      // total number of results
$pagination->getNbPages(); // number of pages of 100 results
```

Any existing `ORDER BY` on the QueryBuilder is replaced by the order configurations. The constructor also accepts
`$fetchJoinCollection` (default `true`) and `$useOutputWalkers` (default `null`), passed to Doctrine's `Paginator`.

### Order configurations

`OrderConfiguration` takes the DQL field, a closure that reads that field's value from a result (used to build the next
cursor), the direction (`orderAscending`, default `true`) and an optional `isUnique` flag:

```php
new OrderConfiguration('u.createdAt', static fn (User $user) => $user->getCreatedAt(), orderAscending: false);
```

The last configuration must point to a unique field (usually the primary key): with only non-unique fields, rows sharing
the same values at a page boundary would be skipped. A single configuration declared with `isUnique: false` throws a
`LogicException` when the second page is fetched.

## Testing & Quality

The test suite runs on an in-memory SQLite database and requires the `pdo_sqlite` extension.

```bash
# Install dependencies
composer install

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
```

## Contributing

Contributions are welcome! Please make sure your changes pass all quality checks before submitting a pull request:

```bash
vendor/bin/phpunit && vendor/bin/phpstan analyse && vendor/bin/psalm && vendor/bin/php-cs-fixer fix --dry-run --diff
```

## License

MIT License. See [LICENSE](LICENSE) for details.

---

<p align="center">
    Built with care by <a href="https://github.com/silarhi">SILARHI</a>.<br>
    If Cursor Pagination saves you time, consider giving it a star on GitHub.
</p>
