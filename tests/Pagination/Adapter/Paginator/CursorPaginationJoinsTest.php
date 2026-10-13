<?php

declare(strict_types=1);

/*
 * This file is part of the Cursor Pagination package.
 *
 * (c) SILARHI <dev@silarhi.fr>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Silarhi\CursorPagination\Tests\Pagination\Adapter\Paginator;

use Silarhi\CursorPagination\Tests\Pagination\Adapter\DoctrinePaginatorAdapterTrait;
use Silarhi\CursorPagination\Tests\Pagination\CursorPaginationJoinsTestCase;

final class CursorPaginationJoinsTest extends CursorPaginationJoinsTestCase
{
    use DoctrinePaginatorAdapterTrait;
}
