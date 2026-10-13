---
title: Adapters
description: The PaginatorAdapterInterface, and the adapters for Doctrine's Paginator and CursorPaginator.
sidebar:
    order: 4
---

`CursorPagination` builds the query of each page, its order and its cursor condition. Running it, limited to
`$maxPerPages` results, and counting the results, is the job of an adapter, in the `Silarhi\CursorPagination\Adapter`
namespace. Two adapters wrap Doctrine's own paginators:

| Adapter                          | Doctrine class                                         | doctrine/orm  |
| -------------------------------- | ------------------------------------------------------ | ------------- |
| `DoctrineCursorPaginatorAdapter` | `Doctrine\ORM\Tools\Pagination\CursorPaginator`        | 3.7 and later |
| `DoctrinePaginatorAdapter`       | `Doctrine\ORM\Tools\Pagination\Paginator` (deprecated) | 3.x, not 4    |

## Default

Without the `$adapter` argument, `CursorPagination` uses `DoctrineCursorPaginatorAdapter` when `CursorPaginator`
exists (doctrine/orm 3.7 and later, 4 included), and `DoctrinePaginatorAdapter` otherwise. Pass an adapter to choose:

```php
use Silarhi\CursorPagination\Adapter\DoctrinePaginatorAdapter;

$pagination = new CursorPagination($queryBuilder, $configurations, 100, adapter: new DoctrinePaginatorAdapter());
```

Both give the same results. They differ in the queries they run:

- **The last page.** `CursorPaginator` fetches one more row than `$maxPerPages` to tell whether a next page exists:
  the iteration ends after the last page. `Paginator` cannot tell a full page from the last one, so the next page is
  queried, and comes back empty when the number of results is a multiple of `$maxPerPages`.
- **The count.** `CursorPaginator` only counts the results of a page it fetched: `DoctrineCursorPaginatorAdapter`
  builds the same count query with Doctrine's `CountWalker` and `CountOutputWalker`. See
  [counting](/guides/fetch-joins/#counting).

`CursorPaginator` is called without a Doctrine cursor: `CursorPagination` keeps its own cursor condition, which handles
any order expression, and reads its values with the closures of the order configurations, scalar rows included.

## PaginatorAdapterInterface

```php
interface PaginatorAdapterInterface
{
    public function fetchPage(Query $query, int $maxResults, bool $fetchJoinCollection, ?bool $useOutputWalkers): PageResult;

    public function count(Query $query, ?bool $useOutputWalkers): int;
}
```

- **`fetchPage()`** returns the first `$maxResults` results of `$query`, in the order of its `ORDER BY`, which already
  holds the cursor condition. With `$fetchJoinCollection`, `$maxResults` counts root entities: the page's distinct
  ids, then their rows. `PageResult::$hasMore` tells whether a next page may hold results; `false` ends the iteration
  without querying it.
- **`count()`** counts the results of `$query`: distinct root entities, or rows when the query's
  `CountWalker::HINT_DISTINCT` hint is `false`.

`CursorPagination` creates a new `Query` for each call: an adapter may change it. `$useOutputWalkers` is the
`CursorPagination` argument, or the choice made for the count (see [output walkers](/guides/fetch-joins/#output-walkers));
`null` means Doctrine's default.
