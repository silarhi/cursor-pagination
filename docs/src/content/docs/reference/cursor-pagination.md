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
)
```

| Argument               | Default | Description                                                                                                                                     |
| ---------------------- | ------- | ----------------------------------------------------------------------------------------------------------------------------------------------- |
| `$queryBuilder`        |         | The query to paginate. It is cloned for each page: its `ORDER BY` is replaced by the order configurations, its max results by `$maxPerPages`.   |
| `$orderConfigurations` |         | The order of the pages, ending with a unique field. See [OrderConfigurations](/reference/order-configurations/).                                |
| `$maxPerPages`         | `100`   | Rows per page, and size of the chunks of `getChunkResults()`. Use a positive value.                                                             |
| `$fetchJoinCollection` | `null`  | Doctrine `Paginator`'s flag. `null` enables it only when the query has joins or several root entities. See [fetch joins](/guides/fetch-joins/). |
| `$useOutputWalkers`    | `null`  | Passed to `Paginator::setUseOutputWalkers()`. See [output walkers](/guides/fetch-joins/#output-walkers).                                        |

:::caution
Leave the first result of the `QueryBuilder` unset: it is kept on every page, and would skip rows on each of them.
:::

## Methods

### getResults()

```php
public function getResults(): Generator // Generator<int, T>
```

Yields the results one by one, page after page. Each call starts from the first page, with its own cursor. The keys
run from `0` across all pages.

Throws a `LogicException` when the second page is fetched if the order is a single configuration declared with
`isUnique: false`.

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

The total number of results, from Doctrine's `Paginator`. It runs once, then is cached by the instance. Also available
as `count($pagination)`.

### getNbPages()

```php
public function getNbPages(): int
```

`ceil(count() / $maxPerPages)`, or `0` when `$maxPerPages` is not positive.
