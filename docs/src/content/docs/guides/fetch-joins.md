---
title: Fetch joins and collections
description: How the fetchJoinCollection option picks one or two queries per page, and when to set it.
sidebar:
    order: 3
---

`CursorPagination` runs every page through Doctrine's `Paginator`, whose `fetchJoinCollection` flag decides how a page
is limited.

## Why joins need two queries

`LIMIT` counts SQL rows, not entities. When a query fetch-joins a collection (`u.groups`), a user with three groups
takes three rows: a page of 100 rows can hold fewer than 100 users, and the last one can lose part of its collection.

With `fetchJoinCollection` enabled, Doctrine runs two queries per page instead:

1. the distinct ids of the page: `SELECT DISTINCT id … LIMIT 100`;
2. their rows, collections included: `SELECT … WHERE id IN (…)`.

Without a join that can multiply the root rows, the second query is wasted: a single `SELECT … LIMIT 100` returns the
same page. The 2.2.0 changelog reports that, on queries without joins, the single query is 4 to 7 times faster on MySQL
and PostgreSQL.

## Automatic detection

The `$fetchJoinCollection` argument defaults to `null`, which picks the strategy from the query:

| Query                                      | Strategy | Queries per page |
| ------------------------------------------ | -------- | ---------------- |
| No join, a single root entity              | disabled | 1                |
| Any join, or several root entities         | enabled  | 2                |
| An empty page (the last one, or no result) | either   | 1                |

Any join enables it, even a many-to-one join, or a join only used in the `WHERE` clause: the detection does not look at
the associations.

## Forcing it

Pass `true` or `false` to override the detection:

```php
$pagination = new CursorPagination($queryBuilder, $configurations, 100, fetchJoinCollection: false);
```

- **`false` when the joins are all to-one** (`u.company`, a filter on a many-to-one association): they cannot multiply
  the root rows, so one query per page is enough.
- **`true`** keeps the behavior of the versions before 2.2.0, where it was the default.

:::danger[Never false with a to-many join]
With `fetchJoinCollection: false` and a to-many join, a page holds fewer results than `$maxPerPages` as soon as a root
entity has several joined rows. `CursorPagination` takes a short page for the last one: the iteration stops there, and
the remaining rows are skipped without error. Keep the default, or `true`.
:::

## Scalar rows

Doctrine's `Paginator` refuses `fetchJoinCollection` on a query that only selects scalar fields (`Paginators do not
support Queries which only yield ScalarResults`). A scalar query without join is detected as such; a scalar query
with joins needs `fetchJoinCollection: false`. See [scalar rows and memory](/guides/scalar-rows/).

## Output walkers

`$useOutputWalkers` (default `null`) is passed to `Paginator::setUseOutputWalkers()`. It changes how Doctrine rewrites
the queries it derives from yours: the count query, and the distinct-ids query of the fetch-join strategy. With `null`,
Doctrine uses its output walkers unless the query already sets a custom one.

On SQLite with doctrine/orm 3.7, for a query without join, `count()` gives:

```sql
-- useOutputWalkers: null (output walker): the whole query wrapped in a subquery
SELECT COUNT(*) AS dctrn_count FROM (SELECT DISTINCT id_0 FROM (SELECT u0_.id AS id_0, … FROM User u0_) dctrn_result) dctrn_table

-- useOutputWalkers: false (tree walker)
SELECT count(DISTINCT u0_.id) AS sclr_0 FROM User u0_
```

The count is distinct in both cases, whatever `fetchJoinCollection` is. Pass `false` for the simpler query, or when the
count of a [scalar query](/guides/scalar-rows/#counting-scalar-rows) fails; keep `null` if Doctrine reports that a
query needs the output walkers.
