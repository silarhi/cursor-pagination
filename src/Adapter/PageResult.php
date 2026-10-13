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

/**
 * The results of one page, as fetched by a {@see PaginatorAdapterInterface}.
 */
final readonly class PageResult
{
    /**
     * @param list<mixed> $items
     * @param bool        $hasMore whether a next page may hold results: false ends the iteration without querying it
     */
    public function __construct(
        public array $items,
        public bool $hasMore,
    ) {
    }
}
