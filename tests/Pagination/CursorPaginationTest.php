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

use function count;

use PHPUnit\Framework\Attributes\DataProvider;
use Silarhi\CursorPagination\Configuration\OrderConfiguration;
use Silarhi\CursorPagination\Configuration\OrderConfigurations;
use Silarhi\CursorPagination\Pagination\CursorPagination;
use Silarhi\CursorPagination\Tests\DoctrineTestCase;
use Silarhi\CursorPagination\Tests\Entity\User;

final class CursorPaginationTest extends DoctrineTestCase
{
    public function testSimplePagination(): void
    {
        $pagination = $this->getSimpleCursorPagination();

        $expectedResults = range(1, 10);
        $index = 0;
        foreach ($pagination->getResults() as $result) {
            self::assertInstanceOf(User::class, $result);
            self::assertEquals($expectedResults[$index], $result->getId());
            ++$index;
        }
        self::assertEquals(count($expectedResults), $index);

        $index = 0;
        $chunks = 0;
        foreach ($pagination->getChunkResults() as $results) {
            foreach ($results as $result) {
                self::assertInstanceOf(User::class, $result);
                self::assertEquals($expectedResults[$index], $result->getId());
                ++$index;
            }
            ++$chunks;
        }
        self::assertEquals(count($expectedResults), $index);
        self::assertEquals(ceil(count($expectedResults) / 2), $chunks);
    }

    /**
     * @dataProvider provideInverse
     */
    #[DataProvider('provideInverse')]
    public function testComplexPagination(bool $inverseConfigurations, bool $reverseOrder): void
    {
        $queryBuilder = $this
            ->entityManager
            ->getRepository(User::class)
            ->createQueryBuilder('u')
        ;

        $orderConfigurations = [
            new OrderConfiguration('u.id', fn (User $user) => $user->getId(), !$reverseOrder),
            new OrderConfiguration('u.number', fn (User $user) => $user->getNumber(), !$reverseOrder),
        ];

        if ($inverseConfigurations) {
            $orderConfigurations = array_reverse($orderConfigurations);
        }

        $configurations = new OrderConfigurations(...$orderConfigurations);

        /** @var CursorPagination<User> $pagination */
        $pagination = new CursorPagination($queryBuilder, $configurations, 2);

        $expectedResults = range(1, 10);
        if ($reverseOrder) {
            $expectedResults = array_reverse($expectedResults);
        }

        $index = 0;
        foreach ($pagination->getResults() as $result) {
            self::assertInstanceOf(User::class, $result);
            self::assertEquals($expectedResults[$index], $result->getId());
            ++$index;
        }
        self::assertEquals(count($expectedResults), $index);

        $index = 0;
        $chunks = 0;
        foreach ($pagination->getChunkResults() as $results) {
            foreach ($results as $result) {
                self::assertInstanceOf(User::class, $result);
                self::assertEquals($expectedResults[$index], $result->getId());
                ++$index;
            }
            ++$chunks;
        }
        self::assertEquals(count($expectedResults), $index);
        self::assertEquals(ceil(count($expectedResults) / 2), $chunks);
    }

    public function testComplexReversedPagination(): void
    {
        $configurations = new OrderConfigurations(
            new OrderConfiguration('u.tenantId', fn (User $user) => $user->getTenantId()),
            new OrderConfiguration('u.id', fn (User $user) => $user->getId()),
        );

        $queryBuilder = $this
            ->entityManager
            ->getRepository(User::class)
            ->createQueryBuilder('u')
        ;

        /** @var CursorPagination<User> $pagination */
        $pagination = new CursorPagination($queryBuilder, $configurations, 2);

        $expectedResults = [1, 2, 3, 7, 8, 9, 4, 5, 6, 10];
        $index = 0;
        foreach ($pagination->getResults() as $result) {
            self::assertInstanceOf(User::class, $result);
            self::assertEquals($expectedResults[$index], $result->getId());
            ++$index;
        }
        self::assertEquals(count($expectedResults), $index);

        $index = 0;
        $chunks = 0;
        foreach ($pagination->getChunkResults() as $results) {
            foreach ($results as $result) {
                self::assertInstanceOf(User::class, $result);
                self::assertEquals($expectedResults[$index], $result->getId());
                ++$index;
            }
            ++$chunks;
        }
        self::assertEquals(count($expectedResults), $index);
        self::assertEquals(ceil(count($expectedResults) / 2), $chunks);
    }

    /**
     * @return iterable<int, array<int, bool>>
     */
    public static function provideInverse(): iterable
    {
        yield [true, true];
        yield [true, false];
        yield [false, true];
        yield [false, false];
    }

    /**
     * @dataProvider provideInverse
     */
    #[DataProvider('provideInverse')]
    public function testCount(bool $loadResultsBeforeCount): void
    {
        $pagination = $this->getSimpleCursorPagination();

        if ($loadResultsBeforeCount) {
            iterator_to_array($pagination->getResults());
        }

        self::assertEquals(10, $pagination->count());
        self::assertEquals(10, count($pagination));
    }

    /**
     * @return iterable<int, array<int, bool>>
     */
    public function provideLoadResults(): iterable
    {
        yield [true];
        yield [false];
    }

    public function testGetPages(): void
    {
        $pagination = $this->getSimpleCursorPagination();

        self::assertEquals(5, $pagination->getNbPages());
    }

    /**
     * @return CursorPagination<User>
     */
    private function getSimpleCursorPagination(): CursorPagination
    {
        $queryBuilder = $this
            ->entityManager
            ->getRepository(User::class)
            ->createQueryBuilder('u');

        /** @var CursorPagination<User> $pagination */
        $pagination = new CursorPagination($queryBuilder, new OrderConfigurations(
            new OrderConfiguration('u.id', fn (User $user) => $user->getId()),
        ), 2);

        return $pagination;
    }
}
