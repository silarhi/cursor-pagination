---
title: Doctrine's CursorPaginator
description: CursorPagination compared with the CursorPaginator of doctrine/orm 3.7 and 4, and when to use which.
sidebar:
    order: 2
---

doctrine/orm 3.7 ships its own keyset paginator, `Doctrine\ORM\Tools\Pagination\CursorPaginator`, and keeps it in
doctrine/orm 4, where the old `Paginator` is removed. Both build the same kind of query: the order of the query, and a
condition that starts after the last row of the previous page. They serve different uses.

## Two different jobs

**Doctrine's `CursorPaginator` serves one page per call**, for an API or a "load more" button: the client gets an
opaque cursor string with the page, and sends it back to get the next one, or the previous one.

```php
use Doctrine\ORM\Tools\Pagination\CursorPaginator;

$queryBuilder = $userRepository->createQueryBuilder('u')->orderBy('u.createdAt')->addOrderBy('u.id');

$page = (new CursorPaginator(20))->paginate($queryBuilder, $request->query->get('cursor'));

return [
    'users' => $page->getItems(),
    'next' => $page->hasNextPage() ? $page->getNextCursorAsString() : null,
];
```

**`CursorPagination` reads every page in one loop**, for a batch: an export, a migration, a reindexing. It yields the
rows one by one or by chunks, and fetches the next page on its own.

```php
$pagination = new CursorPagination($queryBuilder, new OrderConfigurations(
    new OrderConfiguration('u.createdAt', static fn (User $user) => $user->getCreatedAt()),
    new OrderConfiguration('u.id', static fn (User $user) => $user->getId()),
), 1000);

foreach ($pagination->getChunkResults() as $users) {
    // ...
    $entityManager->flush();
    $entityManager->clear();
}
```

On doctrine/orm 3.7 and later, `CursorPagination` runs its pages through `CursorPaginator`: see
[adapters](/reference/adapters/). It passes no Doctrine cursor, and keeps its own cursor condition.

## Compared

|                                     | `CursorPagination`                                               | Doctrine's `CursorPaginator`                                       |
| ----------------------------------- | ---------------------------------------------------------------- | ------------------------------------------------------------------ |
| doctrine/orm                        | 3.0 and later                                                    | 3.7 and later                                                      |
| Pages per call                      | all of them, as a generator                                      | one                                                                |
| Previous page                       | no                                                               | yes                                                                |
| Cursor for a client                 | no: resume with a `WHERE` condition                              | yes: an encoded string, or a `Cursor`                              |
| Order                               | `OrderConfigurations`, replaces the `ORDER BY`                   | the `ORDER BY` of the query                                        |
| Order expressions                   | any DQL expression (`IDENTITY(p.author)`, a function)            | fields and result variables only, `TypeError` otherwise            |
| Field of a joined entity (`a.name`) | yes                                                              | the page works, building the next cursor fails                     |
| Cursor values                       | read by your closures                                            | read from the entity metadata                                      |
| Scalar rows, `NEW` DTOs             | yes                                                              | the page works, building the next cursor fails (`$item::class`)    |
| When the cursor is read             | before the page is yielded: changes in the loop never skip rows  | when you ask for it: a change to the last row moves it             |
| Collection joins                    | detected: one query per page without them, two with them         | `queryProducesDuplicates`, `true` by default: two queries per page |
| Last page                           | fetches one more row (with `CursorPaginator`)                    | fetches one more row                                               |
| Count                               | `count()`, without fetching a page, a plain `COUNT(*)` if it can | `getTotalCount()` on a fetched page, always a distinct count       |
| Leading index bound                 | `a >= :x AND (a > :x OR …)`                                      | `a > :x OR (a = :x AND …)` only                                    |
| `NULL` in an ordered field          | rows skipped: order on non-nullable fields                       | rows skipped                                                       |

The details below were checked against doctrine/orm 3.7.5 and its `4.0.x` branch.

### When the cursor is read

`CursorPaginator` builds the next cursor when `getNextCursor()` is called, from the entity as it is then. In a batch
that changes an ordered field, the cursor moves with the change:

```php
$cursor = null;
do {
    $page = $paginator->paginate($queryBuilder, $cursor); // ordered by u.number, then u.id
    foreach ($page as $user) {
        $user->setNumber($user->getNumber() + 100);
    }
    $entityManager->flush();

    $cursor = $page->hasNextPage() ? $page->getNextCursor() : null; // after the number of the last user changed
} while (null !== $cursor);
```

With 10 users and pages of 3, this loop reads users 1, 2 and 3, then stops: the next page starts after a number of
103, and the 7 other users are skipped without error.

`CursorPagination` reads the cursor values before yielding the page, so a change made in the loop never moves its
cursor, and never skips rows. A row moved ahead of the cursor is still read again in a later page, though: with the
loop above, the users would come back with numbers of 101 to 110, then 201 to 210, and so on. With either paginator,
order on fields the loop does not change. See [what the cursor does not do](/concepts/how-it-works/#what-the-cursor-does-not-do).

### Fields of joined entities and scalar rows

`CursorPaginator` reads the cursor values with the metadata of the result's class. Ordering posts by their author's
`a.number` reads `number` on the `Post`, and fails with `Call to a member function getValue() on null`. A scalar row is
an array, and fails with `Cannot use "::class" on array`. In both cases the first page is fetched; the error comes
with `getNextCursor()`.

## Which one

- **An API, infinite scroll, "load more"**: Doctrine's `CursorPaginator`. A request asks for one page, the client
  keeps the cursor, and it can go back.
- **A batch over a large table**: `CursorPagination`. It walks every page, with chunks for `flush()` and `clear()`,
  scalar rows to keep the memory flat, and a cursor that does not follow the changes made in the loop.
