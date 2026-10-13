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
use Silarhi\CursorPagination\Adapter\DoctrinePaginatorAdapter;
use Silarhi\CursorPagination\Adapter\PaginatorAdapterInterface;
use Silarhi\CursorPagination\Configuration\OrderConfigurations;
use Silarhi\CursorPagination\Pagination\CursorPagination;
use Silarhi\CursorPagination\Tests\DoctrineTestCase;

/**
 * Runs the same tests with each adapter: see the subclasses in Adapter/.
 */
abstract class PaginationTestCase extends DoctrineTestCase
{
    /**
     * Skips the test when the adapter's Doctrine paginator does not exist in the installed doctrine/orm.
     */
    abstract protected function createAdapter(): PaginatorAdapterInterface;

    /**
     * @return CursorPagination<mixed>
     */
    protected function createPagination(
        QueryBuilder $queryBuilder,
        OrderConfigurations $orderConfigurations,
        int $maxPerPages = 100,
        ?bool $fetchJoinCollection = null,
        ?bool $useOutputWalkers = null,
    ): CursorPagination {
        return new CursorPagination($queryBuilder, $orderConfigurations, $maxPerPages, $fetchJoinCollection, $useOutputWalkers, $this->createAdapter());
    }

    /**
     * Queries run for an empty page after a full last one: Doctrine's Paginator cannot tell that it was the last.
     */
    protected function trailingEmptyPageQueries(): int
    {
        return $this->createAdapter() instanceof DoctrinePaginatorAdapter ? 1 : 0;
    }
}
