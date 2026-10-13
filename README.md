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

<p align="center">
    📖 <a href="https://cursor-pagination.silarhi.dev"><strong>Documentation</strong></a>
</p>

---

## Features

- **Keyset pagination** — each page is fetched with a `WHERE` clause built from the last row of the previous page.
- **Multi-field ordering** — combine several fields, each ascending or descending, ending with a unique one.
- **Item or chunk iteration** — iterate entity by entity, or by arrays of `maxPerPages` items for flush/clear batches.
- **Restartable iterations** — every iteration starts from the beginning, even if a previous one was stopped early.
- **Fetch joins handled** — one query per page, or two when the query joins a collection or another entity (`JOIN … WITH`): the page's ids, then their rows.
- **Query analysis** — collection joins and scalar rows are detected; misconfigured orders throw instead of skipping rows.
- **Counting** — `count()` and `getNbPages()`, with a plain `COUNT` when no join can duplicate rows.
- **Ready for doctrine/orm 4** — runs on Doctrine's `CursorPaginator` (doctrine/orm 3.7+), or its `Paginator` before 3.7.

## Requirements

PHP 8.2+ and doctrine/orm ^3.0.

## Installation

```bash
composer require silarhi/cursor-pagination
```

## Quick start

```php
use Silarhi\CursorPagination\Configuration\OrderConfiguration;
use Silarhi\CursorPagination\Configuration\OrderConfigurations;
use Silarhi\CursorPagination\Pagination\CursorPagination;

$queryBuilder = $entityManager->getRepository(User::class)->createQueryBuilder('u');

/** @var CursorPagination<User> $pagination */
$pagination = new CursorPagination($queryBuilder, new OrderConfigurations(
    new OrderConfiguration('u.createdAt', static fn (User $user): \DateTimeImmutable => $user->getCreatedAt()),
    new OrderConfiguration('u.id', static fn (User $user): int => $user->getId()),
), 100);

foreach ($pagination->getChunkResults() as $users) {
    // up to 100 users per chunk
    $entityManager->flush();
    $entityManager->clear();
}
```

## Documentation

Everything else is on **[cursor-pagination.silarhi.dev](https://cursor-pagination.silarhi.dev)**:

- [Installation](https://cursor-pagination.silarhi.dev/getting-started/installation/) and [quick start](https://cursor-pagination.silarhi.dev/getting-started/quick-start/)
- [How it works](https://cursor-pagination.silarhi.dev/concepts/how-it-works/): keyset pagination against `OFFSET`
- Guides: [ordering and unique keys](https://cursor-pagination.silarhi.dev/guides/ordering/), [composite cursors](https://cursor-pagination.silarhi.dev/guides/composite-cursors/),
  [fetch joins and collections](https://cursor-pagination.silarhi.dev/guides/fetch-joins/), [scalar rows and memory](https://cursor-pagination.silarhi.dev/guides/scalar-rows/),
  [batch processing patterns](https://cursor-pagination.silarhi.dev/guides/batch-processing/)
- Reference: [CursorPagination](https://cursor-pagination.silarhi.dev/reference/cursor-pagination/),
  [OrderConfiguration(s)](https://cursor-pagination.silarhi.dev/reference/order-configurations/), [ChunkIterator](https://cursor-pagination.silarhi.dev/reference/chunk-iterator/)

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Release notes are in [CHANGELOG.md](CHANGELOG.md).

## License

Released under the [MIT License](LICENSE).
