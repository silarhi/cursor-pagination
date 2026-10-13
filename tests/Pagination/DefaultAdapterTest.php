<?php

declare(strict_types=1);

/*
 * This file is part of the Cursor Pagination package.
 *
 * (c) SILARHI <dev@silarhi.fr>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Silarhi\CursorPagination\Tests\Pagination;

use Doctrine\ORM\Tools\Pagination\CursorPaginator;
use Silarhi\CursorPagination\Configuration\OrderConfiguration;
use Silarhi\CursorPagination\Configuration\OrderConfigurations;
use Silarhi\CursorPagination\Pagination\CursorPagination;
use Silarhi\CursorPagination\Tests\DoctrineTestCase;
use Silarhi\CursorPagination\Tests\Entity\User;

final class DefaultAdapterTest extends DoctrineTestCase
{
    public function testCursorPaginatorIsUsedWhenItExists(): void
    {
        $queryBuilder = $this
            ->entityManager
            ->getRepository(User::class)
            ->createQueryBuilder('u');

        /** @var CursorPagination<User> $pagination */
        $pagination = new CursorPagination($queryBuilder, new OrderConfigurations(
            new OrderConfiguration('u.id', static fn (User $user) => $user->getId()),
        ), 5);

        $this->queryCounter->reset();
        self::assertCount(10, iterator_to_array($pagination->getResults(), false));

        // 2 full pages: Doctrine's CursorPaginator (doctrine/orm 3.7+) knows the second is the last, its Paginator
        // queries an empty third page
        self::assertSame(class_exists(CursorPaginator::class) ? 2 : 3, $this->queryCounter->count);
    }
}
