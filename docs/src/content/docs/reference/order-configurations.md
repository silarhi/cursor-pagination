---
title: OrderConfiguration(s)
description: The OrderConfiguration value object and the OrderConfigurations collection.
sidebar:
    order: 2
---

Both live in the `Silarhi\CursorPagination\Configuration` namespace. See
[ordering and unique keys](/guides/ordering/) for how to choose the fields.

## OrderConfiguration

A `final readonly` value object describing one ordered field.

```php
public function __construct(
    string $fieldName,
    Closure $fieldValueGetter,
    bool $orderAscending = true,
    ?bool $isUnique = null,
)
```

| Argument            | Default | Description                                                                                                |
| ------------------- | ------- | ---------------------------------------------------------------------------------------------------------- |
| `$fieldName`        |         | The DQL field, with its alias: `u.createdAt`.                                                              |
| `$fieldValueGetter` |         | Receives one result (entity or array) and returns the field's value, used for the cursor of the next page. |
| `$orderAscending`   | `true`  | `false` sorts descending.                                                                                  |
| `$isUnique`         | `null`  | `false` on every configuration throws a `LogicException` before the first page.                            |

| Method                   | Returns                       |
| ------------------------ | ----------------------------- |
| `getFieldName()`         | `string`: the DQL field       |
| `getFieldValueGetter()`  | `Closure`: the value getter   |
| `isOrderAscending()`     | `bool`                        |
| `getOrderByExpression()` | `string`: `'ASC'` or `'DESC'` |
| `isUnique()`             | `?bool`                       |

## OrderConfigurations

A `final`, ordered collection of `OrderConfiguration`s. It implements `IteratorAggregate`, `Countable` and
`ArrayAccess`; the configurations apply in the order they are stored.

```php
$configurations = new OrderConfigurations(
    new OrderConfiguration('u.createdAt', static fn (User $user) => $user->getCreatedAt()),
    new OrderConfiguration('u.id', static fn (User $user) => $user->getId()),
);
```

The constructor is variadic and does not accept named arguments: pass the configurations positionally, or spread an
array with `...`.

| Method                                           | Description                                             |
| ------------------------------------------------ | ------------------------------------------------------- |
| `add(OrderConfiguration $orderConfiguration)`    | Appends a configuration.                                |
| `remove(OrderConfiguration $orderConfiguration)` | Removes that instance, and renumbers the keys from `0`. |
| `clear()`                                        | Removes every configuration.                            |
| `getOrderConfigurations()`                       | `array<int, OrderConfiguration>`                        |
| `getIterator()`                                  | `ArrayIterator<int, OrderConfiguration>`                |
| `count()`                                        | The number of configurations.                           |

### Array access

```php
$configurations[] = $configuration;  // appends, like add()
$configurations[0] = $configuration; // replaces the configuration at key 0
$configurations[0];                  // reads it
isset($configurations[1]);
unset($configurations[1]);           // removes it, without renumbering the keys
```

Setting anything other than an `OrderConfiguration`, or using an offset that is not an integer, throws an
`InvalidArgumentException`. The keys name the cursor parameters (`:cursor_parameter_<key>`).
