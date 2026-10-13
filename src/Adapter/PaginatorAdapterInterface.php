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

use Doctrine\ORM\Query;
use Doctrine\ORM\Tools\Pagination\CountWalker;

/**
 * Runs the page and count queries of a CursorPagination with one of Doctrine's paginators. The cursor condition is
 * already in the query: an adapter only limits it, fetches it and counts it. CursorPagination creates a new query for
 * each call: an adapter may change it.
 */
interface PaginatorAdapterInterface
{
    /**
     * Fetches the first $maxResults results of $query, in the order of its ORDER BY.
     *
     * @param bool      $fetchJoinCollection whether $maxResults counts root entities instead of SQL rows: the
     *                                       page's distinct ids, then their rows
     * @param bool|null $useOutputWalkers    whether Doctrine's output walkers build the distinct-ids query, null
     *                                       for Doctrine's default
     */
    public function fetchPage(Query $query, int $maxResults, bool $fetchJoinCollection, ?bool $useOutputWalkers): PageResult;

    /**
     * Counts the results of $query: its distinct root entities, or its rows when the
     * {@see CountWalker::HINT_DISTINCT} hint is false (output walkers always count distinct root entities, or the
     * groups of a grouped query).
     *
     * @param bool|null $useOutputWalkers whether Doctrine's output walkers build the count query, null for
     *                                    Doctrine's default
     */
    public function count(Query $query, ?bool $useOutputWalkers): int;
}
