# A doctrine ORM cursor based pagination library for faster batch operations

![Build Status](https://github.com/silarhi/cursor-pagination/actions/workflows/continuous-integration.yml/badge.svg)
[![Coverage](https://img.shields.io/endpoint?url=https%3A%2F%2Fraw.githubusercontent.com%2Fsilarhi%2Fcursor-pagination%2Fbadges%2Fcoverage.json)](https://github.com/silarhi/cursor-pagination/actions/workflows/continuous-integration.yml)
[![Latest Stable Version](https://poser.pugx.org/silarhi/cursor-pagination/v/stable)](https://packagist.org/packages/silarhi/cursor-pagination)
[![Total Downloads](https://poser.pugx.org/silarhi/cursor-pagination/downloads)](https://packagist.org/packages/silarhi/cursor-pagination)
[![License](https://poser.pugx.org/silarhi/cursor-pagination/license)](https://packagist.org/packages/silarhi/cursor-pagination)

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
    ->where('u.enabled = true');

$configurations = new OrderConfigurations(
    new OrderConfiguration('u.createdAt', static fn (User $user): string => $user->getCreatedAt()),
    new OrderConfiguration('u.id', static fn (User $user): int => $user->getId()),
);

/** @var CursorPagination<User> $pagination */
$pagination = new CursorPagination($queryBuilder, $configurations, 100);

// Method 1: get results as chunk (recommended)
foreach($pagination->getChunkResults() as $results) {
    foreach($results as $user) {
         $user->setEnabled(false);
    }

    $entityManager->flush();
    $entityManager->clear();
}

// Method 2: get single result as iterator
foreach($pagination->getResults() as $user) {
    // do something with user
}

``
```
