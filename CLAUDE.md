# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

`silarhi/cursor-pagination` is a PHP library for cursor-based (keyset) pagination over Doctrine ORM queries: instead of `OFFSET`, each page continues after the last row of the previous one (`WHERE (a, b) > (last a, last b)`), so iterating a large table costs the same on page 1 and page 10,000. Published on Packagist under MIT license.

## Development Commands

```bash
# Install dependencies
composer install

# Run tests (SQLite in memory, no database to set up)
vendor/bin/phpunit
vendor/bin/phpunit tests/Pagination/CursorPaginationTest.php  # Single file
vendor/bin/phpunit --filter testFetchJoinCollection           # Single method

# Static analysis
vendor/bin/phpstan analyse   # level max + strict rules, src and tests
vendor/bin/psalm             # error level 7

# Code style
vendor/bin/php-cs-fixer fix

# Rector
vendor/bin/rector process --dry-run

# composer.json validation + normalization
composer validate --strict
composer normalize --dry-run
```

## Architecture

### Pagination (`src/Pagination/`)

- `CursorPagination` (`final`, `IteratorAggregate` + `Countable`): takes a `QueryBuilder`, an `OrderConfigurations`, `$maxPerPages` (default 100), `$fetchJoinCollection` and `$useOutputWalkers`.
    - `getResults()` is a generator yielding entities page by page. Any `ORDER BY` of the query builder is replaced by the order configurations. Before the first page, it validates the configurations (`LogicException` when empty or all `isUnique: false`) and the query builder (`LogicException` on a first result, also in `count()`), then parses the query once (`QueryAnalysisWalker::analyse()`). Each page clones the base query builder, applies the cursor, and runs it through the adapter (`PaginatorAdapterInterface::fetchPage()`); `PageResult::$hasMore` false ends the iteration without querying an empty page. Order configurations on anything but `alias.field` are also selected as `<expr> AS HIDDEN cursor_order_<n>` and ordered by that alias (`CursorWalker` throws a `TypeError` on other ORDER BY expressions, even without a cursor); the cursor condition keeps the expression. Order directions go to `addOrderBy()` as `\SortDirection` cases when its signature accepts them (doctrine/orm 3.7+, required by 4), as `'ASC'`/`'DESC'` before (`sortDirection()`, reflection). The cursor is local to the generator, so every iteration starts from the beginning, even after a previous one was stopped early, and concurrent iterations do not share it.
    - The cursor values of the last row are read **before** the page is yielded, so code changing the ordered fields while iterating does not shift the next page.
    - `getChunkResults()` yields arrays of up to `$maxPerPages` entities (`ChunkIterator`); `count()` (cached) and `getNbPages()` use the adapter's `count()`, with `CountWalker::HINT_DISTINCT` and the output walkers chosen from the analysis: a plain `COUNT(*)` through the tree walker when no join can multiply rows, distinct root ids through Doctrine's default (output walkers) otherwise, rows (or groups) for scalar-only queries.
    - `applyCursor()` builds `a > :p0 OR (a = :p0 AND b > :p1) OR …` (`<` for descending configurations): DQL has no row-value comparison. With two or more configurations it also adds a redundant `a >= :p0` (`<=` descending) on the leading column. It never changes the matching rows, but lets PostgreSQL seek the index instead of scanning it from the start on every page (MySQL's range optimizer already handles the OR form). Keep it.
    - `$fetchJoinCollection` (`?bool`, default `null`): whether a page is limited by root entities (two queries: the page's distinct ids, then their rows) instead of SQL rows. `null` means auto (`resolveFetchJoinCollection()`, `QueryAnalysis::hydratesDuplicateRows()`): enabled for collection joins and arbitrary joins (`JOIN Entity e ON …`, `WITH` before doctrine/orm 3.6, forbidden in 4), not for to-one joins, `SELECT DISTINCT` of the root entity alone, or scalar-only queries. Explicit `true`/`false` are passed through: `false` with a collection join skips rows (short pages end the iteration), documented as a danger box, not checked. Without joins the single query is 4 to 7 times faster on MySQL and PostgreSQL.
- `Internal/` (`@internal`): `QueryAnalysisWalker` is a tree walker run once per iteration/count on a parse of the query (`getSQL()` with `expireQueryCache()`, so a cached parser result does not skip it); it reads the AST and query components (collection/arbitrary joins, DISTINCT root, scalar-only, grouped) and stores a `QueryAnalysis` in a query hint. Keep it to that: checks that need more analysis (NULL handling, rejecting `fetchJoinCollection: false`) were deliberately left to the documentation.

### Adapter (`src/Adapter/`)

- `PaginatorAdapterInterface`: `fetchPage(Query, $maxResults, $fetchJoinCollection, $useOutputWalkers): PageResult` (items + `hasMore`) and `count(Query, $useOutputWalkers)` (honours `CountWalker::HINT_DISTINCT`). `CursorPagination` creates a new `Query` per call, so adapters may change it. `CursorPagination`'s last constructor argument `$adapter`; `null` = `DoctrineCursorPaginatorAdapter` when `Doctrine\ORM\Tools\Pagination\CursorPaginator` exists (3.7+), `DoctrinePaginatorAdapter` otherwise.
- `DoctrinePaginatorAdapter`: Doctrine's `Paginator` (deprecated in 3.7, removed in 4). `hasMore` = full page, so a full last page is followed by an empty one.
- `DoctrineCursorPaginatorAdapter`: Doctrine's `CursorPaginator`, called **without** a Doctrine cursor (our cursor condition is already in the query, handles any order expression, reads values through the closures; Doctrine's only knows `alias.field` keys and builds cursors from entity metadata, which fails on scalar rows). It fetches `limit + 1` rows, so `hasMore` is exact. `CursorPaginator` only counts through a fetched page, so `count()` builds the count query itself with `CountWalker` / `CountOutputWalker`, as Doctrine's `PaginatorQuery::getCountQuery()` does (a `Query` clone loses its parameters and hints: the query is changed in place).

### Configuration (`src/Configuration/`)

- `OrderConfiguration` (`final readonly`): the DQL field (`u.createdAt`), a closure reading that field's value from a result (used to build the next cursor), the direction (`orderAscending`, default `true`), and `isUnique` (only `false` is checked: all-`false` throws). Ordering on nullable columns is the user's responsibility (documented, not detected): end the order with the id.
- `OrderConfigurations` (`final`): ordered collection of `OrderConfiguration`s, `IteratorAggregate`, `Countable` and `ArrayAccess` (int offsets only, `InvalidArgumentException` otherwise); `$configurations[] = …` appends. The array keys name the cursor parameters (`:cursor_parameter_<key>`).

### Iterator (`src/Iterator/`)

- `ChunkIterator` (`final readonly`): groups any iterable into arrays of `$size` items, keys preserved, last chunk possibly shorter.

## Testing

- `tests/DoctrineTestCase.php` boots an `EntityManager` on SQLite in memory with the `tests/Entity` entities and loads 10 users and 10 posts with a nullable many-to-one `author` (`User::$posts` is the one-to-many side), from `tests/Fixtures/UserDataLoader.php` (called directly: doctrine/data-fixtures conflicts with doctrine/orm 4; its constants hold the fixture values for expected results). Native lazy objects are enabled on PHP 8.4+ with doctrine/orm 3.4 to 3.x (always on in 4), proxies otherwise (the lowest-dependencies CI job); the metadata configuration uses `createAttributeMetadataConfig()` from 3.5 (the older name is removed in 4).
- The pagination tests are abstract `*TestCase` classes extending `tests/Pagination/PaginationTestCase.php` (`createPagination()`, `trailingEmptyPageQueries()`), run once per adapter by the `final` subclasses in `tests/Pagination/Adapter/{Paginator,CursorPaginator}/` (traits `Doctrine*AdapterTrait` skip when the Doctrine class does not exist: `CursorPaginator` before 3.7, `Paginator` in 4). Create paginations with `$this->createPagination()`, not `new CursorPagination()`, and add query-count expectations for a full last page with `trailingEmptyPageQueries()`. Arbitrary joins in tests use `ON` from doctrine/orm 3.6, `WITH` before.
- To try doctrine/orm 4 before its release: in a scratch copy, require `doctrine/orm: 4.0.x-dev` with `minimum-stability: dev` and run phpunit (the `Paginator` tests are skipped).
- `$this->queryCounter` (`tests/QueryCounter.php`, a PSR-3 logger plugged into DBAL's logging middleware) counts executed queries and keeps their SQL (`reset()` before the code under test): use it to assert queries per page (e.g. `testFetchJoinCollection`) or the count query (`CursorPaginationJoinsTest::testCountQuery`; doctrine/orm < 3.3 renders a plain count as `count(u0_.id)` instead of `count(*)`). Its `$message` parameter stays untyped for psr/log 1.x.
- The test entity's table is `User`, a reserved word on PostgreSQL (unquoted `user` means `CURRENT_USER`): rename it in metadata if tests or benchmarks ever run on PostgreSQL.

## Code Standards

- PHP 8.2+ required, `declare(strict_types=1)` and the SILARHI header comment in all files
- PHPStan level max with strict rules (`self::assert*()` in tests), Psalm error level 7
- Symfony PHP-CS-Fixer ruleset with `@Symfony:risky`
- Public classes are `final`: extend behaviour by composition

## Dependency Constraints

- `doctrine/orm` stays `^3.0`: the lowest-dependencies CI job tests its minimal version, and Renovate only widens it for new majors (`rangeStrategy: widen`). Never raise its floor.
- QA tools are bumped to their latest releases. `config.bump-after-update: "dev"` also raises the other dev dependencies (`symfony/cache`) on a full `composer update`: check those floors before committing a `composer.json` touched by an update.
- `composer.lock` is not committed.

## API Documentation

**Always update documentation when adding or modifying public API**: `CursorPagination` (constructor arguments,
methods), `OrderConfiguration`, `OrderConfigurations`, `ChunkIterator`, the generated cursor condition, exceptions.

1. **`docs/`** — the documentation site (cursor-pagination.silarhi.dev, Starlight with `@silarhi/docs-kit`): guides
   and reference pages. Build it with `yarn install && yarn build` in `docs/`. Keep `README.md` short: update its
   features list or quick start only when they change.
2. **`CLAUDE.md`** — Architecture.
3. **PHPDoc blocks** — accurate `@param`, `@return` and `@throws` on public methods.
4. **`CHANGELOG.md`** — see below.

## Changelog & Releases

- **`CHANGELOG.md`** follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). Every user-facing change gets an entry in the top section, titled with the next release, never a bare `[Unreleased]`: the next minor (e.g. `[2.3.0] - Unreleased`), or the next major for a BC break. Prefix breaking changes with **BC break:** and say what users must change. Skip dependency bumps, CI and tooling changes.
- **Releases**: date the section and point its compare link at the tag (`compare/<previous>...<tag>`, no `v` prefix), then tag `X.Y.Z` on `origin/main` once CI is green, with a GitHub release whose notes follow that section. Packagist updates from the GitHub hook.
