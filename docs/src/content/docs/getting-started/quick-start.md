---
title: Quick start
description: Paginate a Doctrine QueryBuilder with a cursor, item by item or by chunks.
sidebar:
    order: 2
---

A `CursorPagination` takes three things: a Doctrine `QueryBuilder`, the order of the pages (an `OrderConfigurations`
collection), and the number of results per page.

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
```

Each `OrderConfiguration` names a DQL field and a closure that reads that field's value from a result: the closures
build the cursor of the next page from the last row of the current one. The order must end with a unique field,
usually the primary key. See [ordering and unique keys](/guides/ordering/).

Any `ORDER BY` already on the `QueryBuilder` is replaced by the order configurations.

## Iterate by chunks

`getChunkResults()` yields arrays of up to `$maxPerPages` results. It is the recommended way to modify entities: flush
and clear the entity manager once per chunk.

```php
// Method 1: get results as chunks (recommended)
foreach ($pagination->getChunkResults() as $users) {
    foreach ($users as $user) {
        $user->setEnabled(false);
    }

    $entityManager->flush();
    $entityManager->clear();
}
```

The example disables the users it reads, so they no longer match `u.enabled = true`. With `OFFSET` pagination, that
would shift the next pages and skip rows; the cursor is not affected. See [how it works](/concepts/how-it-works/).

## Iterate item by item

`getResults()` is a generator yielding the results one by one, fetching the next page when the current one is
consumed. `CursorPagination` is also iterable directly, with the same results.

```php
// Method 2: get results one by one ($pagination is also iterable directly)
foreach ($pagination->getResults() as $user) {
    // do something with $user
}

foreach ($pagination as $user) {
    // same results
}
```

Every iteration starts from the first page, even if a previous one was stopped early with `break`.

## Count

```php
$pagination->count();      // total number of results
$pagination->getNbPages(); // number of pages of 100 results
```

`CursorPagination` is `Countable`, so `count($pagination)` works too. The count runs once, then is cached by the
instance.

## Next steps

- [How it works](/concepts/how-it-works/): keyset pagination against `OFFSET`
- [Fetch joins and collections](/guides/fetch-joins/): queries with joins
- [Scalar rows and memory](/guides/scalar-rows/): read large tables without hydrating entities
- [Batch processing patterns](/guides/batch-processing/)
