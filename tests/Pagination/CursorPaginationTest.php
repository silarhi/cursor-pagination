<?php

declare(strict_types=1);

/*
 * This file is part of the CFONB Parser package.
 *
 * (c) SILARHI <dev@silarhi.fr>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Silarhi\CursorPagination\Tests\Pagination;

use Silarhi\CursorPagination\Configuration\OrderConfiguration;
use Silarhi\CursorPagination\Configuration\OrderConfigurations;
use Silarhi\CursorPagination\Pagination\CursorPagination;
use Silarhi\CursorPagination\Tests\DoctrineTestCase;
use Silarhi\CursorPagination\Tests\Entity\User;

class CursorPaginationTest extends DoctrineTestCase
{
    public function testSimplePagination(): void
    {
        $queryBuilder = $this
            ->entityManager
            ->getRepository(User::class)
            ->createQueryBuilder('u')
        ;

        /** @var CursorPagination<User> $pagination */
        $pagination = new CursorPagination($queryBuilder, new OrderConfigurations(
            new OrderConfiguration('u.id', fn (User $user) => $user->getId()),
        ), 2);

        $expectedResults = range(1, 10);
        $index = 0;
        foreach ($pagination->getResults() as $result) {
            self::assertInstanceOf(User::class, $result);
            self::assertEquals($expectedResults[$index], $result->getId());
            ++$index;
        }

        $index = 0;
        foreach ($pagination->getChunkResults() as $results) {
            foreach ($results as $result) {
                self::assertInstanceOf(User::class, $result);
                self::assertEquals($expectedResults[$index], $result->getId());
                ++$index;
            }
        }
    }

    /**
     * @dataProvider provideInverse
     */
    public function testComplexPagination(bool $inverse): void
    {
        $queryBuilder = $this
            ->entityManager
            ->getRepository(User::class)
            ->createQueryBuilder('u')
        ;
        if ($inverse) {
            $configurations = new OrderConfigurations(
                new OrderConfiguration('u.number', fn (User $user) => $user->getNumber()),
                new OrderConfiguration('u.id', fn (User $user) => $user->getId()),
            );
        } else {
            $configurations = new OrderConfigurations(
                new OrderConfiguration('u.id', fn (User $user) => $user->getId()),
                new OrderConfiguration('u.number', fn (User $user) => $user->getNumber()),
            );
        }

        /** @var CursorPagination<User> $pagination */
        $pagination = new CursorPagination($queryBuilder, $configurations, 2);

        $expectedResults = range(1, 10);
        $index = 0;
        foreach ($pagination->getResults() as $result) {
            self::assertInstanceOf(User::class, $result);
            self::assertEquals($expectedResults[$index], $result->getId());
            ++$index;
        }

        $index = 0;
        foreach ($pagination->getChunkResults() as $results) {
            foreach ($results as $result) {
                self::assertInstanceOf(User::class, $result);
                self::assertEquals($expectedResults[$index], $result->getId());
                ++$index;
            }
        }
    }

    /**
     * @return iterable<int, array<int, bool>>
     */
    public function provideInverse(): iterable
    {
        yield [true];
        yield [false];
    }
}
