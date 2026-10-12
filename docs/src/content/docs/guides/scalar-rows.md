---
title: Scalar rows and memory
description: Keep the memory flat on large tables, by clearing the entity manager or selecting scalar fields.
sidebar:
    order: 4
---

`CursorPagination` loads one page at a time, but the entity manager keeps every entity it hydrates: without care, a run
over a million rows ends with a million managed entities in memory.

## Clear the entity manager per chunk

When you need entities, iterate by chunks and clear the entity manager after each one. The entities of the chunk are
detached; the next page is hydrated from scratch.

```php
foreach ($pagination->getChunkResults() as $users) {
    foreach ($users as $user) {
        // ...
    }

    $entityManager->flush();
    $entityManager->clear();
}
```

The cursor of the next page is read from the last row before the chunk is handed to you, so clearing, or changing the
ordered fields, does not affect it. See [batch processing patterns](/guides/batch-processing/) for the pitfalls of
clearing inside `getResults()`.

## Select scalar fields

When you only read data (an export, a sitemap, a search index), select scalar fields: rows are hydrated as arrays,
with no entity, no proxy and nothing in the identity map. The closures of the order configurations then receive arrays,
keyed by the selected field names:

```php
$queryBuilder = $entityManager
    ->getRepository(User::class)
    ->createQueryBuilder('u')
    ->select('u.id, u.email, u.createdAt')
    ->where('u.enabled = true');

/** @var CursorPagination<array{id: int, email: string, createdAt: \DateTimeImmutable}> $pagination */
$pagination = new CursorPagination(
    $queryBuilder,
    new OrderConfigurations(
        new OrderConfiguration('u.createdAt', static fn (array $user): \DateTimeImmutable => $user['createdAt']),
        new OrderConfiguration('u.id', static fn (array $user): int => $user['id'], isUnique: true),
    ),
    1000,
);

foreach ($pagination->getResults() as $user) {
    fputcsv($output, [$user['id'], $user['email'], $user['createdAt']->format('c')]);
}
```

Doctrine's `toIterable()` is not a replacement: it hydrates one row at a time, but entities stay in the identity map,
and a buffered driver such as `pdo_mysql` with its default settings holds the whole result set client-side.

### Joins and scalar rows

Doctrine's `Paginator` throws on a scalar query with `fetchJoinCollection` enabled
(`Paginators do not support Queries which only yield ScalarResults`). Without join, the automatic detection disables
it. With joins, pass `fetchJoinCollection: false`, and remember that each scalar row is a SQL row: with a to-many join,
end the order with a field that is unique per row (the joined entity's id), or rows sharing the cursor at a page
boundary are skipped. See [fetch joins and collections](/guides/fetch-joins/).

### Counting scalar rows

On doctrine/orm 3.7, `count()` fails on a scalar query with the default output walkers (`Not all identifier properties
can be found in the ResultSetMapping`). Pass `useOutputWalkers: false` if you need the count:

```php
$pagination = new CursorPagination($queryBuilder, $configurations, 1000, useOutputWalkers: false);
$pagination->count();
```

## Generators

A generator over `getResults()` keeps the memory flat end to end: each row is read, transformed and handed over before
the next one. The [generators guide](https://llms-txt-bundle.silarhi.dev/guides/generators/) of the LLMs.txt Bundle
uses this library that way, to write an `llms.txt` file of any size.
