# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.4.0] - 2026-10-13

### Added

- Ready for doctrine/orm 4 (tested against its `4.0.x` branch; the `doctrine/orm` constraint is widened when 4.0 is released), through adapters in `Silarhi\CursorPagination\Adapter`: `CursorPagination` runs its page and count queries with a `PaginatorAdapterInterface`. `DoctrineCursorPaginatorAdapter` uses Doctrine's `CursorPaginator` (doctrine/orm 3.7+) and is the default whenever it exists; `DoctrinePaginatorAdapter` uses Doctrine's `Paginator`, deprecated in doctrine/orm 3.7 and removed in 4.0, and is the default before 3.7. Pass an adapter as the new last argument of `CursorPagination`, `$adapter`, to choose one. Both return the same results: `CursorPaginator` is called without a Doctrine cursor, the cursor condition stays the one of `CursorPagination`.

### Changed

- On doctrine/orm 3.7 and later, a full last page is no longer followed by a query for an empty page: `CursorPaginator` fetches one more row to tell whether a next page exists.
- On doctrine/orm 3.7 and later, the order directions are passed to `QueryBuilder::addOrderBy()` as `SortDirection` enum cases instead of the `'ASC'` and `'DESC'` strings it deprecates.
- An order configuration on an expression other than `alias.field` (`IDENTITY(p.author)`, a function) is also selected as a hidden result variable, `cursor_order_<n>`, which the `ORDER BY` uses: `CursorPaginator` only orders by fields and result variables. Do not use these names in your queries.

## [2.3.0] - 2026-10-13

### Changed

- The automatic `fetchJoinCollection` detection parses the query: it is only enabled for collection (to-many) joins and arbitrary joins (`JOIN Entity e WITH …`). A query with to-one joins only, a `SELECT DISTINCT` of the root entity, or a query that only selects scalar fields, takes one query per page instead of two.
- `count()` runs a plain `COUNT(*)` when no join can duplicate the rows, instead of `COUNT(DISTINCT id)` wrapped in subqueries by the output walkers. On a query that only selects scalar fields, it counts the rows (joined rows included), or the groups of a grouped query, without `useOutputWalkers: false`.
- Order configurations all declared `isUnique: false` throw a `LogicException`, with several configurations too. The check, and the one for a single configuration, run before the first page instead of the second.
- An empty `OrderConfigurations` throws a `LogicException`, instead of fetching the first page again and again.
- A `QueryBuilder` with a first result (`setFirstResult()`) makes `getResults()` and `count()` throw a `LogicException`: the offset was applied to every page, skipping rows on each of them.
- A `QueryBuilder` that does not build a `SELECT` query (`update()`, `delete()`) makes `count()` throw a `LogicException` (`CursorPagination only supports SELECT queries.`).

### Fixed

- `ChunkIterator` declared `@implements IteratorAggregate<TKey, TValue>` while it yields chunks: static analysers now see `IteratorAggregate<int, array<TKey, TValue>>` when iterating it directly.

## [2.2.0] - 2026-10-12

### Changed

- `CursorPagination`'s `$fetchJoinCollection` argument defaults to `null` (auto) instead of `true`: Doctrine's fetch-join-collection strategy is only used when the query has joins or several root entities, the only cases where root rows can be duplicated. Without joins, a page takes 1 query instead of 2 (a `SELECT DISTINCT id … LIMIT` then `… WHERE id IN (…)`), 4 to 7 times faster on MySQL and PostgreSQL. The results are the same. `count()` is unchanged: Doctrine's `Paginator` always counts distinct root entities, whatever `fetchJoinCollection` is (this entry first said otherwise). Any join enables it, even a many-to-one one. Pass `true` to keep the previous behaviour; an explicit `true` or `false` is honoured as before. The parameter type is now `?bool`.
- Composite cursors add a redundant range bound on their leading column: for an order on `(a, b)`, `a >= :x AND (a > :x OR (a = :x AND b > :y))` (`<=` when descending). It never changes which rows match, but lets PostgreSQL seek an index on the leading column instead of scanning it from its first entry on every page.
- `OrderConfigurations` throws an `InvalidArgumentException` when an offset that is not an integer is set through array access (`$configurations['foo'] = …`), instead of casting it to an integer.

## [2.1.1] - 2026-10-01

### Changed

- `OrderConfigurations` throws an `InvalidArgumentException` when anything other than an `OrderConfiguration` is set through array access. The constructor and `add()` already required that type.

### Fixed

- With doctrine/collections 3 (PHP 8.4+, installed by recent doctrine/orm 3 releases), `OrderConfiguration::getOrderByExpression()` failed with `Undefined constant Doctrine\Common\Collections\Criteria::ASC`, breaking every pagination. It returns plain `'ASC'` / `'DESC'`, which works with every supported version.
- Stopping an iteration early (a `break` in a `foreach`) made the next iteration over the same `CursorPagination` resume where the previous one stopped, silently skipping rows, and two interleaved iterations shared one cursor. Every `foreach`, `getResults()` and `getChunkResults()` now starts from the beginning with its own cursor.
- Appending with `$configurations[] = $configuration` replaced the first `OrderConfiguration` instead of adding one: it now works like `add()`.

## [2.1.0] - 2026-01-29

No functional changes: dependency and tooling updates only.

## [2.0.0] - 2025-01-06

### Changed

- **BC break:** PHP 8.2 or later is required: stay on 1.x for PHP 8.0 and 8.1.
- **BC break:** doctrine/orm 3 is required: stay on 1.x for doctrine/orm 2.

## [1.2.0] - 2024-06-05

### Added

- `CursorPagination` implements `IteratorAggregate`: iterate over it directly with `foreach`, like `getResults()`.
- `CursorPagination` implements `Countable`: `count()` returns the total number of results through Doctrine's `Paginator`, run once and cached by the instance, and `getNbPages()` the number of pages.

### Changed

- **BC break:** `CursorPagination`, `OrderConfiguration`, `OrderConfigurations` and `ChunkIterator` are `final`: wrap them instead of extending them.

## [1.1.0] - 2024-03-13

### Changed

- A single `OrderConfiguration` declared with `isUnique: false` throws a `LogicException`: the cursor needs a unique ordering.

### Fixed

- Paginating on several `OrderConfiguration`s skipped rows: the cursor was `a >= :x AND b > :y`, which excluded the rows with a greater `a` whose `b` was not greater than `y`. It is now `a > :x OR (a = :x AND b > :y)`, and `isUnique` no longer changes the condition.
- `getResults()` returned nothing when called a second time on the same `CursorPagination`: the cursor is reset once an iteration completes.

## [1.0.2] - 2024-02-09

### Added

- Support for doctrine/orm 3.

### Changed

- The PHP requirement is `>=8.0` instead of being capped at PHP 8.3.

## [1.0.1] - 2024-02-01

### Fixed

- Rows were skipped when the fields used for ordering were modified while iterating: the cursor was read from the last row of a page after the caller had consumed the page. It is now read before the page's rows are yielded.

## [1.0.0] - 2023-12-05

First release: cursor-based (keyset) pagination over a Doctrine ORM `QueryBuilder`, for PHP 8.0 to 8.3 and doctrine/orm 2.5+.

### Added

- `CursorPagination`, configured by an `OrderConfigurations` collection of `OrderConfiguration`s (a DQL field, a closure reading its value from a result, the direction, and whether it is unique). `getResults()` yields the results one by one and `getChunkResults()` by pages of `$maxPerPages` (100 by default), each page fetched with `WHERE` conditions on the last row of the previous one instead of an `OFFSET`.
- `ChunkIterator`, which splits any iterable into arrays of a given size.

[2.4.0]: https://github.com/silarhi/cursor-pagination/compare/2.3.0...2.4.0
[2.3.0]: https://github.com/silarhi/cursor-pagination/compare/2.2.0...2.3.0
[2.2.0]: https://github.com/silarhi/cursor-pagination/compare/2.1.1...2.2.0
[2.1.1]: https://github.com/silarhi/cursor-pagination/compare/2.1.0...2.1.1
[2.1.0]: https://github.com/silarhi/cursor-pagination/compare/2.0.0...2.1.0
[2.0.0]: https://github.com/silarhi/cursor-pagination/compare/1.2.0...2.0.0
[1.2.0]: https://github.com/silarhi/cursor-pagination/compare/1.1.0...1.2.0
[1.1.0]: https://github.com/silarhi/cursor-pagination/compare/1.0.2...1.1.0
[1.0.2]: https://github.com/silarhi/cursor-pagination/compare/1.0.1...1.0.2
[1.0.1]: https://github.com/silarhi/cursor-pagination/compare/1.0.0...1.0.1
[1.0.0]: https://github.com/silarhi/cursor-pagination/releases/tag/1.0.0
