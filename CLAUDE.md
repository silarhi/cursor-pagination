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
    - `getResults()` is a generator yielding entities page by page. Any `ORDER BY` of the query builder is replaced by the order configurations. Each page clones the base query builder, applies the cursor, and runs it through Doctrine's `Paginator`. The cursor is local to the generator, so every iteration starts from the beginning, even after a previous one was stopped early, and concurrent iterations do not share it.
    - The cursor values of the last row are read **before** the page is yielded, so code changing the ordered fields while iterating does not shift the next page.
    - `getChunkResults()` yields arrays of up to `$maxPerPages` entities (`ChunkIterator`); `count()` (cached) and `getNbPages()` use the `Paginator` count.
    - `applyCursor()` builds `a > :p0 OR (a = :p0 AND b > :p1) OR …` (`<` for descending configurations): DQL has no row-value comparison. With two or more configurations it also adds a redundant `a >= :p0` (`<=` descending) on the leading column. It never changes the matching rows, but lets PostgreSQL seek the index instead of scanning it from the start on every page (MySQL's range optimizer already handles the OR form). Keep it.
    - `$fetchJoinCollection` (`?bool`, default `null`) is Doctrine `Paginator`'s flag. `null` means auto (`resolveFetchJoinCollection()`): enabled only when the query has DQL joins or several root entities, the only cases where joins may duplicate root rows. Enabled, Doctrine runs two queries per page (the page's distinct ids, then their rows); disabled, one. Explicit `true`/`false` are passed through. Without joins the single query is 4 to 7 times faster on MySQL and PostgreSQL.
    - A single order configuration must be unique: one marked `isUnique: false` makes `applyCursor()` throw a `LogicException`, since the cursor would skip rows sharing its value (`isUnique` defaults to `null`, which is not checked). With several configurations, make the last one unique (usually the id) so the order is total.

### Configuration (`src/Configuration/`)

- `OrderConfiguration` (`final readonly`): the DQL field (`u.createdAt`), a closure reading that field's value from a result (used to build the next cursor), the direction (`orderAscending`, default `true`) and `isUnique`.
- `OrderConfigurations` (`final`): ordered collection of `OrderConfiguration`s, `IteratorAggregate`, `Countable` and `ArrayAccess` (int offsets only, `InvalidArgumentException` otherwise); `$configurations[] = …` appends. The array keys name the cursor parameters (`:cursor_parameter_<key>`).

### Iterator (`src/Iterator/`)

- `ChunkIterator` (`final readonly`): groups any iterable into arrays of `$size` items, keys preserved, last chunk possibly shorter.

## Testing

- `tests/DoctrineTestCase.php` boots an `EntityManager` on SQLite in memory with the `tests/Entity/User` entity and loads 10 users (`tests/Fixtures/UserDataLoader.php`, via doctrine/data-fixtures). Native lazy objects are enabled on PHP 8.4+ with doctrine/orm 3.4+, proxies otherwise (the lowest-dependencies CI job).
- `$this->queryCounter` (`tests/QueryCounter.php`, a PSR-3 logger plugged into DBAL's logging middleware) counts executed queries: use it to assert queries per page (e.g. `testFetchJoinCollection`). Its `$message` parameter stays untyped for psr/log 1.x.
- The test entity's table is `User`, a reserved word on PostgreSQL (unquoted `user` means `CURRENT_USER`): rename it in metadata if tests or benchmarks ever run on PostgreSQL.

## Code Standards

- PHP 8.2+ required, `declare(strict_types=1)` and the SILARHI header comment in all files
- PHPStan level max with strict rules (`self::assert*()` in tests), Psalm error level 7
- Symfony PHP-CS-Fixer ruleset with `@Symfony:risky`
- Public classes are `final`: extend behaviour by composition

## Dependency Constraints

- `doctrine/orm` stays `^3.0`: the lowest-dependencies CI job tests its minimal version, and Renovate only widens it for new majors (`rangeStrategy: widen`). Never raise its floor.
- QA tools are bumped to their latest releases. `config.bump-after-update: "dev"` also raises the other dev dependencies (`symfony/cache`, `doctrine/data-fixtures`) on a full `composer update`: check those floors before committing a `composer.json` touched by an update.
- `composer.lock` is not committed.

## Changelog & Releases

- **`CHANGELOG.md`** follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). Every user-facing change gets an entry in the top section, titled with the next release, never a bare `[Unreleased]`: the next minor (e.g. `[2.3.0] - Unreleased`), or the next major for a BC break. Prefix breaking changes with **BC break:** and say what users must change. Skip dependency bumps, CI and tooling changes.
- **Releases**: date the section and point its compare link at the tag (`compare/<previous>...<tag>`, no `v` prefix), then tag `X.Y.Z` on `origin/main` once CI is green, with a GitHub release whose notes follow that section. Packagist updates from the GitHub hook.
