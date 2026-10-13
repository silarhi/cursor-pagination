<?php

declare(strict_types=1);

/*
 * This file is part of the Cursor Pagination package.
 *
 * (c) SILARHI <dev@silarhi.fr>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Silarhi\CursorPagination\Tests\Pagination\Adapter;

use Doctrine\ORM\Tools\Pagination\CursorPaginator;
use Silarhi\CursorPagination\Adapter\DoctrineCursorPaginatorAdapter;
use Silarhi\CursorPagination\Adapter\PaginatorAdapterInterface;

trait DoctrineCursorPaginatorAdapterTrait
{
    protected function createAdapter(): PaginatorAdapterInterface
    {
        if (!class_exists(CursorPaginator::class)) {
            self::markTestSkipped('Doctrine\'s CursorPaginator needs doctrine/orm 3.7.');
        }

        return new DoctrineCursorPaginatorAdapter();
    }
}
