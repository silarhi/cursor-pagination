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

use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use Silarhi\CursorPagination\Configuration\OrderConfiguration;
use Silarhi\CursorPagination\Configuration\OrderConfigurations;
use Silarhi\CursorPagination\Pagination\CursorPagination;
use Silarhi\CursorPagination\Tests\Entity\Post;
use Silarhi\CursorPagination\Tests\Entity\User;
use Silarhi\CursorPagination\Tests\Fixtures\UserDataLoader;

abstract class CursorPaginationJoinsTestCase extends PaginationTestCase
{
    #[DataProvider('provideUseOutputWalkers')]
    public function testFetchJoinedCollectionIsPaginatedByRootEntity(?bool $useOutputWalkers): void
    {
        $queryBuilder = $this->createUserQueryBuilder()->addSelect('p')->leftJoin('u.posts', 'p');

        /** @var CursorPagination<User> $pagination */
        $pagination = $this->createPagination($queryBuilder, $this->orderByUserId(), 2, useOutputWalkers: $useOutputWalkers);

        $this->queryCounter->reset();
        $postCounts = [];
        foreach ($pagination->getResults() as $user) {
            $postCounts[(int) $user->getId()] = $user->getPosts()->count();
        }

        self::assertSame($this->expectedPostCounts(), $postCounts);
        // 5 full pages of 2 queries (the page's ids, then their rows), then maybe an empty page
        self::assertSame(10 + $this->trailingEmptyPageQueries(), $this->queryCounter->count);
        self::assertSame(10, $pagination->count());
    }

    /**
     * @return iterable<string, array{?bool}>
     */
    public static function provideUseOutputWalkers(): iterable
    {
        yield 'default walkers' => [null];
        yield 'output walkers' => [true];
        yield 'tree walkers' => [false];
    }

    #[DataProvider('provideFetchJoinCollection')]
    public function testDistinctRootEntityWithACollectionJoin(?bool $fetchJoinCollection, int $expectedQueries): void
    {
        $queryBuilder = $this->createUserQueryBuilder()->distinct()->join('u.posts', 'p');

        /** @var CursorPagination<User> $pagination */
        $pagination = $this->createPagination($queryBuilder, $this->orderByUserId(), 2, $fetchJoinCollection);

        $this->queryCounter->reset();
        $ids = array_map(static fn (User $user): ?int => $user->getId(), iterator_to_array($pagination->getResults(), false));

        self::assertSame([1, 3, 4, 7, 10], $ids);
        self::assertSame($expectedQueries, $this->queryCounter->count);
        self::assertSame(5, $pagination->count());
    }

    /**
     * @return iterable<string, array{?bool, int}>
     */
    public static function provideFetchJoinCollection(): iterable
    {
        // 3 pages: 2, 2, then 1 user
        yield 'auto' => [null, 3];
        yield 'disabled' => [false, 3];
        yield 'enabled' => [true, 6];
    }

    public function testToOneJoinRunsASingleQueryPerPage(): void
    {
        $queryBuilder = $this
            ->entityManager
            ->getRepository(Post::class)
            ->createQueryBuilder('p')
            ->addSelect('a')
            ->leftJoin('p.author', 'a');

        /** @var CursorPagination<Post> $pagination */
        $pagination = $this->createPagination($queryBuilder, new OrderConfigurations(
            new OrderConfiguration('p.id', static fn (Post $post) => $post->getId()),
        ), 5);

        $this->queryCounter->reset();
        $authors = [];
        foreach ($pagination->getResults() as $post) {
            $authors[] = $post->getAuthor()?->getId();
        }

        self::assertSame(UserDataLoader::POST_AUTHORS, $authors);
        // 2 full pages, then maybe an empty one: the authors are fetch joined, not loaded one by one
        self::assertSame(2 + $this->trailingEmptyPageQueries(), $this->queryCounter->count);
    }

    #[DataProvider('provideFetchJoinCollectionModes')]
    public function testOrderByAnExpression(?bool $fetchJoinCollection): void
    {
        $queryBuilder = $this
            ->entityManager
            ->getRepository(Post::class)
            ->createQueryBuilder('p')
            ->addSelect('a')
            ->join('p.author', 'a');

        // Doctrine's CursorPaginator only orders by fields and result variables: IDENTITY() goes through a hidden one
        /** @var CursorPagination<Post> $pagination */
        $pagination = $this->createPagination($queryBuilder, new OrderConfigurations(
            new OrderConfiguration('IDENTITY(p.author)', static fn (Post $post) => $post->getAuthor()?->getId(), false),
            new OrderConfiguration('p.id', static fn (Post $post) => $post->getId()),
        ), 2, $fetchJoinCollection);

        $ids = array_map(static fn (Post $post): ?int => $post->getId(), iterator_to_array($pagination->getResults(), false));

        self::assertSame([10, 7, 6, 4, 5, 1, 2, 3], $ids);
        self::assertSame(8, $pagination->count());
    }

    /**
     * @return iterable<string, array{?bool}>
     */
    public static function provideFetchJoinCollectionModes(): iterable
    {
        yield 'auto' => [null];
        yield 'disabled' => [false];
        yield 'enabled' => [true];
    }

    #[DataProvider('provideScalarFetchJoinCollection')]
    public function testScalarRows(?bool $fetchJoinCollection): void
    {
        $queryBuilder = $this->createUserQueryBuilder()->select('u.id, u.tenantId');

        /** @var CursorPagination<array{id: int, tenantId: int}> $pagination */
        $pagination = $this->createPagination($queryBuilder, new OrderConfigurations(
            new OrderConfiguration('u.tenantId', static fn (array $row): mixed => $row['tenantId']),
            new OrderConfiguration('u.id', static fn (array $row): mixed => $row['id']),
        ), 3, $fetchJoinCollection);

        $ids = array_column(iterator_to_array($pagination->getResults(), false), 'id');

        self::assertSame([1, 2, 3, 7, 8, 9, 4, 5, 6, 10], $ids);
        self::assertSame(10, $pagination->count());
    }

    /**
     * @return iterable<string, array{?bool}>
     */
    public static function provideScalarFetchJoinCollection(): iterable
    {
        yield 'auto' => [null];
        yield 'disabled' => [false];
    }

    public function testScalarRowsWithACollectionJoin(): void
    {
        $queryBuilder = $this->createUserQueryBuilder()
            ->select('u.id AS userId, p.id AS postId')
            ->join('u.posts', 'p');

        /** @var CursorPagination<array{userId: int, postId: int}> $pagination */
        $pagination = $this->createPagination($queryBuilder, new OrderConfigurations(
            new OrderConfiguration('u.id', static fn (array $row): mixed => $row['userId']),
            new OrderConfiguration('p.id', static fn (array $row): mixed => $row['postId']),
        ), 3);

        $this->queryCounter->reset();
        $postIds = array_column(iterator_to_array($pagination->getResults(), false), 'postId');

        // Every row is a result: one per post with an author
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 10], $postIds);
        self::assertSame(3, $this->queryCounter->count);
        self::assertSame(8, $pagination->count());
    }

    public function testGroupedScalarRows(): void
    {
        $queryBuilder = $this->createUserQueryBuilder()
            ->select('u.tenantId, COUNT(u.id) AS users')
            ->groupBy('u.tenantId');

        /** @var CursorPagination<array{tenantId: int, users: int}> $pagination */
        $pagination = $this->createPagination($queryBuilder, new OrderConfigurations(
            new OrderConfiguration('u.tenantId', static fn (array $row): mixed => $row['tenantId']),
        ), 1);

        $rows = array_map(
            static fn (array $row): array => [$row['tenantId'], $row['users']],
            iterator_to_array($pagination->getResults(), false),
        );

        self::assertSame([[2, 6], [12, 4]], $rows);
        self::assertSame(2, $pagination->count());
    }

    /**
     * @param callable(QueryBuilder): mixed $prepare
     */
    #[DataProvider('provideCountQueries')]
    public function testCountQuery(callable $prepare, int $expectedCount, string $expectedSqlPattern): void
    {
        $queryBuilder = $this->createUserQueryBuilder();
        $prepare($queryBuilder);

        /** @var CursorPagination<User> $pagination */
        $pagination = $this->createPagination($queryBuilder, $this->orderByUserId());

        $this->queryCounter->reset();

        self::assertSame($expectedCount, $pagination->count());
        $queries = $this->queryCounter->queries;
        self::assertCount(1, $queries);
        self::assertMatchesRegularExpression($expectedSqlPattern, $queries[0] ?? '');
    }

    /**
     * @return iterable<string, array{callable(QueryBuilder): mixed, int, string}>
     */
    public static function provideCountQueries(): iterable
    {
        // A plain count: COUNT(*), or COUNT(u0_.id) before doctrine/orm 3.3
        $plainCount = '/^SELECT count\((\*|u0_\.id)\) AS sclr_0 FROM User u0_/';

        yield 'no join' => [static fn (QueryBuilder $queryBuilder) => $queryBuilder, 10, $plainCount];
        yield 'filtered' => [static fn (QueryBuilder $queryBuilder) => $queryBuilder->where('u.tenantId = 2'), 6, $plainCount];
        yield 'collection join' => [static fn (QueryBuilder $queryBuilder) => $queryBuilder->join('u.posts', 'p'), 5, '/^SELECT COUNT\(\*\) AS dctrn_count FROM \(SELECT DISTINCT /'];
        yield 'scalar rows' => [static fn (QueryBuilder $queryBuilder) => $queryBuilder->select('u.id'), 10, $plainCount];
    }

    private function createUserQueryBuilder(): QueryBuilder
    {
        return $this
            ->entityManager
            ->getRepository(User::class)
            ->createQueryBuilder('u');
    }

    private function orderByUserId(): OrderConfigurations
    {
        return new OrderConfigurations(new OrderConfiguration('u.id', static fn (User $user) => $user->getId()));
    }

    /**
     * @return array<int, int>
     */
    private function expectedPostCounts(): array
    {
        $postCounts = array_fill_keys(range(1, 10), 0);
        foreach (UserDataLoader::POST_AUTHORS as $authorId) {
            if (null !== $authorId) {
                ++$postCounts[$authorId];
            }
        }

        return $postCounts;
    }
}
