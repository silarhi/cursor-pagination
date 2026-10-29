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

use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\Pagination\CountWalker;
use PHPUnit\Framework\Attributes\DataProvider;
use Silarhi\CursorPagination\Tests\Entity\User;

/**
 * The adapters called directly, with queries CursorPagination does not build itself.
 */
abstract class PaginatorAdapterTestCase extends PaginationTestCase
{
    #[DataProvider('provideUseOutputWalkers')]
    public function testCountWithoutDistinctHintCountsRootEntities(?bool $useOutputWalkers): void
    {
        $query = $this->createUserWithPostsQuery();
        self::assertFalse($query->hasHint(CountWalker::HINT_DISTINCT));

        // 8 posts have an author, written by 5 distinct users
        self::assertSame(5, $this->createAdapter()->count($query, $useOutputWalkers));
    }

    public function testCountWithoutDistinctCountsRows(): void
    {
        $query = $this->createUserWithPostsQuery()->setHint(CountWalker::HINT_DISTINCT, false);

        self::assertSame(8, $this->createAdapter()->count($query, false));
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

    private function createUserWithPostsQuery(): Query
    {
        return $this
            ->entityManager
            ->getRepository(User::class)
            ->createQueryBuilder('u')
            ->join('u.posts', 'p')
            ->getQuery();
    }
}
