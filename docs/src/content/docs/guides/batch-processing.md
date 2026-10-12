---
title: Batch processing patterns
description: Update, delete, export or dispatch large sets of rows with CursorPagination.
sidebar:
    order: 5
---

## Update by chunks

Flush and clear once per chunk: one transaction per page, and the memory of one page.

```php
foreach ($pagination->getChunkResults() as $users) {
    foreach ($users as $user) {
        $user->setEnabled(false);
    }

    $entityManager->flush();
    $entityManager->clear();
}
```

The loop may change the fields the query filters on (here `u.enabled`): rows that stop matching do not shift the
next pages, as they would with `OFFSET`. Avoid changing an ordered field so that the row sorts after the cursor: it
would be read again. See [how it works](/concepts/how-it-works/#what-the-cursor-does-not-do).

:::caution[Clearing inside getResults()]
`getResults()` loads a whole page before yielding its first row. Clearing the entity manager in the middle of a page
detaches the rows of that page that are not yielded yet: changes to them are not flushed. Clear between chunks, with
`getChunkResults()`.
:::

## Delete by chunks

Deleting the rows already read does not move the cursor either:

```php
foreach ($pagination->getChunkResults() as $sessions) {
    foreach ($sessions as $session) {
        $entityManager->remove($session);
    }

    $entityManager->flush();
    $entityManager->clear();
}
```

## Dispatch messages per chunk

To hand the work to workers, dispatch one message per chunk of ids. Select the ids only:

```php
$queryBuilder = $userRepository->createQueryBuilder('u')->select('u.id');

$pagination = new CursorPagination(
    $queryBuilder,
    new OrderConfigurations(new OrderConfiguration('u.id', static fn (array $row): int => $row['id'])),
    500,
);

foreach ($pagination->getChunkResults() as $rows) {
    $bus->dispatch(new ReindexUsers(array_column($rows, 'id')));
}
```

The keys of the chunks continue from one chunk to the next (`0` to `499`, then `500` to `999`…): use
`array_values()` if you need a list.

## Show a progress bar

`count()` gives the total for a progress bar:

```php
$progressBar = $io->createProgressBar($pagination->count());

foreach ($pagination->getChunkResults() as $users) {
    // ...
    $progressBar->advance(count($users));
}
```

The count runs once and is cached by the instance: rows changed during the run do not update it.

## Stop early

`break` stops the iteration after the current row. The next `foreach` on the same `CursorPagination` starts again from
the first page.

## Resume an interrupted batch

There is no API to start from a stored cursor: filter the query instead. With an order on the id alone, keep the last
processed id and add it to the query:

```php
$queryBuilder = $userRepository->createQueryBuilder('u')
    ->where('u.id > :lastProcessedId')
    ->setParameter('lastProcessedId', $lastProcessedId);

$pagination = new CursorPagination(
    $queryBuilder,
    new OrderConfigurations(new OrderConfiguration('u.id', static fn (User $user): int => $user->getId())),
);
```

:::caution
Leave the first result (`setFirstResult()`) of the `QueryBuilder` unset: it is not reset, so the offset would apply to
every page and skip rows. Its max results are replaced by `$maxPerPages`.
:::
