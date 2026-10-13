---
title: CursorPagination
description: Constructor arguments and methods of Silarhi\CursorPagination\Pagination\CursorPagination.
sidebar:
    order: 1
---

`Silarhi\CursorPagination\Pagination\CursorPagination` is `final` and implements `IteratorAggregate` and `Countable`.
Its template type `T` is the type of one result: declare it for static analysis.

```php
/** @var CursorPagination<User> $pagination */
$pagination = new CursorPagination($queryBuilder, $configurations, 100);
```

## Constructor

```php
public function __construct(
    QueryBuilder $queryBuilder,
    OrderConfigurations $orderConfigurations,
    int $maxPerPages = 100,
    ?bool $fetchJoinCollection = null,
    ?bool $useOutputWalkers = null,
    ?PaginatorAdapterInterface $adapter = null,
)
```

| Argument               | Default | Description                                                                                                                                                                     |
| ---------------------- | ------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `$queryBuilder`        |         | The query to paginate. It is cloned for each page: its `ORDER BY` is replaced by the order configurations, its max results by `$maxPerPages`. Its first result must stay unset. |
| `$orderConfigurations` |         | The order of the pages, ending with a unique field. See [OrderConfigurations](/reference/order-configurations/).                                                                |
| `$maxPerPages`         | `100`   | Results per page, and size of the chunks of `getChunkResults()`. Use a positive value.                                                                                          |
| `$fetchJoinCollection` | `null`  | Whether a page is limited by root entities (two queries) instead of SQL rows. `null` enables it for collection and arbitrary joins. See [fetch joins](/guides/fetch-joins/).    |
| `$useOutputWalkers`    | `null`  | Whether Doctrine's output walkers build the count and distinct-ids queries. `null` picks them per query. See [output walkers](/guides/fetch-joins/#output-walkers).             |
| `$adapter`             | `null`  | Runs the page and count queries. `null` uses Doctrine's `CursorPaginator` (doctrine/orm 3.7+), its `Paginator` otherwise. See [adapters](/reference/adapters/).                 |

## Exceptions

These misconfigurations would skip rows: they throw a `LogicException` before any row is read.

| Message                                                                | Cause                                                                                                   |
| ---------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| `At least one order configuration is required.`                        | Empty `OrderConfigurations`.                                                                            |
| `When using a single order configuration, it must be unique`           | A single configuration declared `isUnique: false`. See [ordering](/guides/ordering/#the-isunique-flag). |
| `The order configurations are all declared non-unique: …`              | Several configurations, all declared `isUnique: false`.                                                 |
| `The query builder must not have a first result (setFirstResult()): …` | `setFirstResult()` was called on the `QueryBuilder`. Also thrown by `count()`.                          |

Doctrine's own exceptions (an invalid DQL query, a query with several root entities) are not wrapped.

## Methods

### getResults()

```php
public function getResults(): Generator // Generator<int, T>
```

Yields the results one by one, page after page. Each call starts from the first page, with its own cursor. The keys
run from `0` across all pages.

Throws the `LogicException`s listed in [exceptions](#exceptions), on the first iteration. Since it is a generator,
calling it does nothing until the first row is requested.

### getIterator()

```php
public function getIterator(): Generator // Generator<int, T>
```

Same as `getResults()`: `foreach ($pagination as $result)` works directly.

### getChunkResults()

```php
public function getChunkResults(): iterable // iterable<int, array<T>>
```

Yields arrays of up to `$maxPerPages` results, built with [ChunkIterator](/reference/chunk-iterator/) over
`getResults()`: the keys of the results continue from one chunk to the next, and the last chunk may be shorter.

### count()

```php
public function count(): int
```

The total number of results: root entities, or rows for a query that only selects scalar fields (groups for a grouped
one). It runs once, then is cached by the instance. Also available as `count($pagination)`. See
[counting](/guides/fetch-joins/#counting) for the query it runs.

### getNbPages()

```php
public function getNbPages(): int
```

`ceil(count() / $maxPerPages)`, or `0` when `$maxPerPages` is not positive.
