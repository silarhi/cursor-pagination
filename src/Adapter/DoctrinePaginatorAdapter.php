<?php

declare(strict_types=1);

/*
 * This file is part of the Cursor Pagination package.
 *
 * (c) SILARHI <dev@silarhi.fr>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Silarhi\CursorPagination\Adapter;

use function count;

use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Override;

/**
 * Doctrine's Paginator, for doctrine/orm 3: deprecated in doctrine/orm 3.7, removed in 4.0.
 *
 * It cannot tell whether a full page is the last one: the next page is queried, and comes back empty when the
 * number of results is a multiple of the page size.
 */
final class DoctrinePaginatorAdapter implements PaginatorAdapterInterface
{
    #[Override]
    public function fetchPage(Query $query, int $maxResults, bool $fetchJoinCollection, ?bool $useOutputWalkers): PageResult
    {
        $paginator = new Paginator($query->setMaxResults($maxResults), $fetchJoinCollection);
        $paginator->setUseOutputWalkers($useOutputWalkers);

        $items = array_values(iterator_to_array($paginator->getIterator()));

        return new PageResult($items, count($items) >= $maxResults);
    }

    #[Override]
    public function count(Query $query, ?bool $useOutputWalkers): int
    {
        $paginator = new Paginator($query);
        $paginator->setUseOutputWalkers($useOutputWalkers);

        return $paginator->count();
    }
}
