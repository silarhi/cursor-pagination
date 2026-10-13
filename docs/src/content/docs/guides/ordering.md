---
title: Ordering and unique keys
description: Configure the order of the pages with OrderConfiguration, and keep it unique so no row is skipped.
sidebar:
    order: 1
---

The order of the pages is an `OrderConfigurations` collection of `OrderConfiguration`s, one per ordered field. It
replaces any `ORDER BY` of the `QueryBuilder`.

## OrderConfiguration

```php
new OrderConfiguration(
    'u.createdAt',                                    // the DQL field
    static fn (User $user) => $user->getCreatedAt(), // reads that field's value from a result
    orderAscending: true,                             // the direction, true by default
    isUnique: null,                                   // whether the field is unique, null by default
);
```

- **The DQL field** goes in the `ORDER BY` and in the cursor condition, as is: use the alias of the query
  (`u.createdAt`, not `createdAt`).
- **The closure** receives one result, as the query returns it (an entity, or an array for
  [scalar rows](/guides/scalar-rows/)), and returns the value of the field. It is called on the last row of each page to
  build the cursor, so it must return the value that was read from the database, in a type Doctrine can bind as a
  parameter (an `int`, a `string`, a `DateTimeImmutable`…).
- **The direction**: `orderAscending: false` sorts descending, and the cursor then looks for smaller values.
- **`isUnique`**: see [the isUnique flag](#the-isunique-flag).

```php
new OrderConfiguration('u.createdAt', static fn (User $user) => $user->getCreatedAt(), orderAscending: false);
```

## End with a unique field

The cursor starts each page strictly after the last row of the previous one. If several rows share the values of the
ordered fields at a page boundary, the ones that did not fit in the page are skipped. Make the order total by ending it
with a unique field, usually the primary key:

```php
$configurations = new OrderConfigurations(
    new OrderConfiguration('u.createdAt', static fn (User $user) => $user->getCreatedAt()),
    new OrderConfiguration('u.id', static fn (User $user) => $user->getId()),
);
```

A single field that is unique on its own (`u.id`, a unique `u.email`) needs nothing else.

### The isUnique flag

`isUnique` declares whether a field is unique on its own. `CursorPagination` cannot tell whether the fields are unique
together, so it only rejects an order that has no unique field for sure: when every configuration is declared
`isUnique: false`, `getResults()` throws a `LogicException` before the first page. Building the
`OrderConfigurations` does not throw: the check runs when the pagination is iterated.

```php
// Rejected: iterating the pagination throws a LogicException
// ("When using a single order configuration, it must be unique")
new OrderConfigurations(
    new OrderConfiguration('u.tenantId', static fn (User $user) => $user->getTenantId(), isUnique: false),
);

// Rejected: iterating the pagination throws a LogicException
// ("The order configurations are all declared non-unique: …")
new OrderConfigurations(
    new OrderConfiguration('u.tenantId', static fn (User $user) => $user->getTenantId(), isUnique: false),
    new OrderConfiguration('u.createdAt', static fn (User $user) => $user->getCreatedAt(), isUnique: false),
);

// Fine: the order ends with a unique field
new OrderConfigurations(
    new OrderConfiguration('u.tenantId', static fn (User $user) => $user->getTenantId(), isUnique: false),
    new OrderConfiguration('u.id', static fn (User $user) => $user->getId(), isUnique: true),
);
```

The default, `null`, is never checked: an order that ends with a non-unique field without saying so skips rows
silently. Before 2.3.0, only a single configuration was checked, and only when the second page was fetched.

An empty `OrderConfigurations` throws a `LogicException` too: without an order, there is no cursor.

## Avoid nullable fields

SQL comparisons with `NULL` never match: from the second page on, `u.score > :cursor` is false for every row with a
`NULL` score, and a `NULL` in the last row of a page makes the next condition miss rows, or all of them. Those rows are
skipped without error.

:::caution[Order on non-nullable fields]
Choosing the ordered fields is up to you: order on non-nullable columns, and end with the id, which is unique and never
`NULL`. To read the rows with a `NULL` too, paginate them in a separate query (`WHERE u.score IS NULL`, ordered by the
id).
:::

## Index the order

Every page is a range read on the ordered fields: an index on the same columns, in the same order (`created_at, id`),
lets the database seek to the cursor instead of scanning. With mixed directions, see
[composite cursors](/guides/composite-cursors/#indexes).

## Build the collection step by step

`OrderConfigurations` is also a mutable, ordered collection:

```php
$configurations = new OrderConfigurations();
$configurations->add(new OrderConfiguration('u.createdAt', static fn (User $user) => $user->getCreatedAt()));
$configurations[] = new OrderConfiguration('u.id', static fn (User $user) => $user->getId()); // same as add()

count($configurations); // 2
$configurations[0];     // the u.createdAt configuration
```

The fields are applied in the order they were added. See the [reference](/reference/order-configurations/) for every
method.
