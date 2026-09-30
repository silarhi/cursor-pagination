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

use function count;

use LogicException;
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

    #[DataProvider('provideInverse')]
    public function testComplexPagination(bool $inverseConfigurations, bool $reverseOrder): void
    {
        $queryBuilder = $this
            ->entityManager
            ->getRepository(User::class)
            ->createQueryBuilder('u')
        ;

        $orderConfigurations = [
            new OrderConfiguration('u.id', static fn (User $user) => $user->getId(), !$reverseOrder),
            new OrderConfiguration('u.number', static fn (User $user) => $user->getNumber(), !$reverseOrder),
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
            new OrderConfiguration('u.tenantId', static fn (User $user) => $user->getTenantId()),
            new OrderConfiguration('u.id', static fn (User $user) => $user->getId()),
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

    #[DataProvider('provideLoadResults')]
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
    public static function provideLoadResults(): iterable
    {
        yield [true];
        yield [false];
    }

    public function testGetPages(): void
    {
        $pagination = $this->getSimpleCursorPagination();

        self::assertEquals(5, $pagination->getNbPages());
    }

    public function testGetIterator(): void
    {
        $pagination = $this->getSimpleCursorPagination();

        $ids = [];
        foreach ($pagination as $result) {
            self::assertInstanceOf(User::class, $result);
            $ids[] = $result->getId();
        }

        self::assertSame(range(1, 10), $ids);
    }

    #[DataProvider('provideMaxPerPages')]
    public function testPaginationWithIncompleteLastPage(int $maxPerPages, int $expectedChunks): void
    {
        $pagination = $this->getSimpleCursorPagination($maxPerPages);

        $ids = array_map(static fn (User $user): ?int => $user->getId(), iterator_to_array($pagination->getResults(), false));
        self::assertSame(range(1, 10), $ids);

        $chunkSizes = [];
        foreach ($pagination->getChunkResults() as $results) {
            $chunkSizes[] = count($results);
        }
        self::assertCount($expectedChunks, $chunkSizes);
        self::assertSame(10, array_sum($chunkSizes));
        self::assertSame($expectedChunks, $pagination->getNbPages());
    }

    /**
     * @return iterable<string, array{maxPerPages: int, expectedChunks: int}>
     */
    public static function provideMaxPerPages(): iterable
    {
        yield 'last page with a single result' => ['maxPerPages' => 3, 'expectedChunks' => 4];
        yield 'last page with two results' => ['maxPerPages' => 4, 'expectedChunks' => 3];
        yield 'exact number of results' => ['maxPerPages' => 10, 'expectedChunks' => 1];
        yield 'everything in the first page' => ['maxPerPages' => 100, 'expectedChunks' => 1];
    }

    public function testEmptyResults(): void
    {
        $queryBuilder = $this
            ->entityManager
            ->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.id > :id')
            ->setParameter('id', 100)
        ;

        /** @var CursorPagination<User> $pagination */
        $pagination = new CursorPagination($queryBuilder, new OrderConfigurations(
            new OrderConfiguration('u.id', static fn (User $user) => $user->getId()),
        ), 2);

        self::assertSame([], iterator_to_array($pagination->getResults()));
        self::assertSame([], iterator_to_array($pagination->getChunkResults()));
        self::assertSame(0, $pagination->count());
        self::assertSame(0, $pagination->getNbPages());
    }

    #[DataProvider('provideInvalidMaxPerPages')]
    public function testGetNbPagesWithoutPositiveMaxPerPages(int $maxPerPages): void
    {
        $pagination = $this->getSimpleCursorPagination($maxPerPages);

        self::assertSame(0, $pagination->getNbPages());
    }

    /**
     * @return iterable<int, array<int, int>>
     */
    public static function provideInvalidMaxPerPages(): iterable
    {
        yield [0];
        yield [-1];
    }

    public function testSingleNonUniqueOrderConfigurationThrowsWhenCursorIsApplied(): void
    {
        $queryBuilder = $this
            ->entityManager
            ->getRepository(User::class)
            ->createQueryBuilder('u')
        ;

        /** @var CursorPagination<User> $pagination */
        $pagination = new CursorPagination($queryBuilder, new OrderConfigurations(
            new OrderConfiguration('u.tenantId', static fn (User $user) => $user->getTenantId(), isUnique: false),
        ), 2);

        $ids = [];

        try {
            foreach ($pagination->getResults() as $result) {
                $ids[] = $result->getId();
            }
        } catch (LogicException $logicException) {
            self::assertSame('When using a single order configuration, it must be unique', $logicException->getMessage());
            // The first page is fetched without any cursor, the exception is thrown when fetching the second one
            self::assertCount(2, $ids);

            return;
        }

        self::fail('A LogicException should have been thrown.');
    }

    public function testNonUniqueOrderConfigurationIsAllowedWithOtherConfigurations(): void
    {
        $queryBuilder = $this
            ->entityManager
            ->getRepository(User::class)
            ->createQueryBuilder('u')
        ;

        /** @var CursorPagination<User> $pagination */
        $pagination = new CursorPagination($queryBuilder, new OrderConfigurations(
            new OrderConfiguration('u.tenantId', static fn (User $user) => $user->getTenantId(), isUnique: false),
            new OrderConfiguration('u.id', static fn (User $user) => $user->getId(), isUnique: true),
        ), 2);

        $ids = array_map(static fn (User $user): ?int => $user->getId(), iterator_to_array($pagination->getResults(), false));

        self::assertSame([1, 2, 3, 7, 8, 9, 4, 5, 6, 10], $ids);
    }

    /**
     * @return CursorPagination<User>
     */
    private function getSimpleCursorPagination(int $maxPerPages = 2): CursorPagination
    {
        $queryBuilder = $this
            ->entityManager
            ->getRepository(User::class)
            ->createQueryBuilder('u');

        /** @var CursorPagination<User> $pagination */
        $pagination = new CursorPagination($queryBuilder, new OrderConfigurations(
            new OrderConfiguration('u.id', static fn (User $user) => $user->getId()),
        ), $maxPerPages);

        return $pagination;
    }
}
