# A doctrine ORM cursor based pagination library for faster batch operations

## Installation

```bash
composer require silarhi/cursor-pagination
```

## Usage

```php

use Silarhi\CursorPagination\Pagination\CursorPagination;

$queryBuilder = $entityManager
    ->createQueryBuilder('u')
    ->from(User::class, 'u')
    ->where('u.foo = true');

$configurations = new OrderConfigurations(
    new OrderConfiguration('u.createdAt', fn (User $user) => $user->getCreatedAt()),
    new OrderConfiguration('u.id', fn (User $user) => $user->getId()),
);

/** @var CursorPagination<User> $pagination */
$pagination = new CursorPagination($queryBuilder, $configurations, 100);

// Method 1: get results as chunk (recommended)
foreach($pagination->getChunkResults() as $results) {
    foreach($results as $user) {
        // do something with user
    }
    
    $entityManager->flush();
    $entityManager->clear();
}

// Method 2: get single result as iterator
foreach($pagination->getResults() as $user) {
    // do something with user
}

``
