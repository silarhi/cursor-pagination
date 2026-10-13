---
title: Fetch joins and collections
description: How the fetchJoinCollection option picks one or two queries per page, and when to set it.
sidebar:
    order: 3
---

The `fetchJoinCollection` option decides how a page is limited: by SQL rows, or by root entities.

## Why collection joins need two queries

`LIMIT` counts SQL rows, not entities. When a query joins a collection (`u.groups`), a user with three groups takes
three rows: a page of 100 rows can hold fewer than 100 users, and the last one can lose part of its collection.

With `fetchJoinCollection` enabled, each page takes two queries instead:

1. the distinct ids of the page: `SELECT DISTINCT id … LIMIT 100`;
2. their rows, collections included: `SELECT … WHERE id IN (…)`.

Without a join that can multiply the root rows, the second query is wasted: a single `SELECT … LIMIT 100` returns the
same page. The 2.2.0 changelog reports that, on queries without joins, the single query is 4 to 7 times faster on MySQL
and PostgreSQL.

## Automatic detection

The `$fetchJoinCollection` argument defaults to `null`, which picks the strategy from the parsed query:

| Query                                                                      | Strategy | Queries per page |
| -------------------------------------------------------------------------- | -------- | ---------------- |
| No join, or only to-one joins (`u.company`)                                | disabled | 1                |
| A collection join (`u.groups`), fetched or only used in the `WHERE` clause | enabled  | 2                |
| An arbitrary join (`JOIN Group g WITH …`)                                  | enabled  | 2                |
| `SELECT DISTINCT u` with collection joins that are not selected            | disabled | 1                |
| Only scalar fields selected (`SELECT u.id, u.email`)                       | disabled | 1                |
| An empty page (the last one, or no result)                                 | either   | 1                |

Before 2.3.0, any join enabled it, even a to-one join. Several root entities (`FROM User u, Group g`) are not supported:
Doctrine's walkers throw `Cannot count query which selects two FROM components`.

## Forcing it

Pass `true` or `false` to override the detection:

```php
$pagination = new CursorPagination($queryBuilder, $configurations, 100, fetchJoinCollection: true);
```

- **`true`** keeps the behavior of the versions before 2.2.0, where it was the default.
- **`false`** runs one query per page. An arbitrary join (`JOIN Profile p WITH p.user = u`) that matches one row per
  root entity at most is the case where it helps.

:::danger[Never false with a collection join]
With a collection join, a page limited by SQL rows holds fewer than `$maxPerPages` entities as soon as an entity has
several joined rows. `CursorPagination` takes that short page for the last one: the iteration stops there, and the
remaining rows are skipped without error. Keep the default, or `true`. `false` is safe with to-one joins, with
arbitrary joins that match one row per root entity at most, with `SELECT DISTINCT` of the root entity alone, and with
scalar rows.
:::

## Scalar rows

A query that only selects scalar fields returns one result per SQL row, joined rows included: the detection disables
`fetchJoinCollection`, and `count()` counts the rows. Forcing `true` makes Doctrine's output walkers throw
`Paginators do not support Queries which only yield ScalarResults`. See [scalar rows and memory](/guides/scalar-rows/).

## Counting

`count()` picks its query from the same analysis:

| Query                                          | Count query (default walkers)                                       |
| ---------------------------------------------- | ------------------------------------------------------------------- |
| No collection or arbitrary join, no `GROUP BY` | `SELECT COUNT(*) FROM … WHERE …`                                    |
| A collection join, or an arbitrary join        | `SELECT COUNT(*) FROM (SELECT DISTINCT id FROM (…))`: one per root  |
| `GROUP BY` or `HAVING`, scalar fields or not   | `SELECT COUNT(*) FROM (…)`: one per group                           |
| Scalar fields, without `GROUP BY`              | `SELECT COUNT(*) FROM … WHERE …`: one per row, joined rows included |

Before 2.3.0, the count was always distinct (`COUNT(DISTINCT id)`), whatever `fetchJoinCollection` was.

## Output walkers

`$useOutputWalkers` (default `null`) chooses how the derived queries are built from yours: the count query, and the
distinct-ids query of the fetch-join strategy.

- **Output walkers** (`true`) wrap the SQL of your query in a subquery. They handle grouped queries, but their count is
  always distinct, and needs the root identifier in the results, so it fails on scalar rows without `GROUP BY` (`Not all
identifier properties can be found in the ResultSetMapping`).
- **Tree walkers** (`false`) rewrite the DQL of your query: `SELECT count(*) FROM User u0_`, or
  `SELECT count(DISTINCT u0_.id) …` when joins can duplicate rows. They cannot count a query with a `HAVING` clause
  (`Cannot count query that uses a HAVING clause`), and add up one count per group on a grouped query: for scalar
  fields, that is the number of rows behind the groups, not the number of groups.

With `null`, the count of a query that cannot duplicate rows, or of scalar rows without `GROUP BY`, uses the tree
walkers; any other query uses the output walkers, as Doctrine does, unless the query already sets a custom output
walker. Pass `true` or `false` to force one kind for every derived query.
