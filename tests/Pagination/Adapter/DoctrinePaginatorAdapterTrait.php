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

use Doctrine\ORM\Tools\Pagination\Paginator;
use Silarhi\CursorPagination\Adapter\DoctrinePaginatorAdapter;
use Silarhi\CursorPagination\Adapter\PaginatorAdapterInterface;

trait DoctrinePaginatorAdapterTrait
{
    protected function createAdapter(): PaginatorAdapterInterface
    {
        if (!class_exists(Paginator::class)) {
            self::markTestSkipped('Doctrine\'s Paginator is removed in doctrine/orm 4.');
        }

        return new DoctrinePaginatorAdapter();
    }
}
