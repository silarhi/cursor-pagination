---
title: Composite cursors
description: The WHERE clause built from several order configurations, and the indexes it needs.
sidebar:
    order: 2
---

DQL has no row-value comparison (`(a, b) > (:a, :b)`), so the cursor of an order on several fields is expanded into
`OR` conditions, one per field.

## The generated condition

For an order on `u.tenantId` then `u.id`, both ascending, the second page and the following ones get:

```sql
WHERE u.tenantId >= :cursor_parameter_0
  AND (
      u.tenantId > :cursor_parameter_0
      OR (u.tenantId = :cursor_parameter_0 AND u.id > :cursor_parameter_1)
  )
ORDER BY u.tenantId ASC, u.id ASC
```

The parameters hold the values the closures read from the last row of the previous page. With three fields `a`, `b`,
`c`, the expansion is `a > :p0 OR (a = :p0 AND b > :p1) OR (a = :p0 AND b = :p1 AND c > :p2)`, and so on. A descending
field uses `<` instead of `>`, and the leading bound uses `<=` instead of `>=`:

```php
new OrderConfigurations(
    new OrderConfiguration('u.tenantId', static fn (User $user) => $user->getTenantId(), orderAscending: false),
    new OrderConfiguration('u.id', static fn (User $user) => $user->getId()),
);
```

```sql
WHERE u.tenantId <= :cursor_parameter_0
  AND (
      u.tenantId < :cursor_parameter_0
      OR (u.tenantId = :cursor_parameter_0 AND u.id > :cursor_parameter_1)
  )
```

The condition is added with `andWhere()`: the conditions of your `QueryBuilder` still apply. A single order
configuration gives a plain `u.id > :cursor_parameter_0`.

:::caution
The cursor parameters are named `cursor_parameter_<n>`, after the keys of the `OrderConfigurations` collection, and the
hidden result variables of [order expressions](/guides/ordering/#orderconfiguration) `cursor_order_<n>`. Do not use
these names in your own query.
:::

## The leading bound

`u.tenantId >= :cursor_parameter_0` is redundant: the `OR` conditions already imply it, so it never changes which rows
match. It is there for the query planner: a plain range on the leading column lets PostgreSQL seek an index on that
column instead of scanning it from its first entry on every page. MySQL's range optimizer already handles the `OR` form.
It is added since 2.2.0, for every order with two fields or more.

## Indexes

For every page to cost the same, index the ordered columns in the order of the configurations:

```sql
CREATE INDEX user_tenant_id_id ON user (tenant_id, id);
```

Put the columns of your equality filters first: for `WHERE u.enabled = true` ordered by `u.createdAt, u.id`, an index
on `(enabled, created_at, id)` serves both the filter and the cursor.

With mixed directions (`tenantId` descending, `id` ascending), an index can only be read in one direction at a time:
create one whose column directions match the order, or whose directions are all reversed, if your database supports
descending index columns.
