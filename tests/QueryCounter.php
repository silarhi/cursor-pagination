<?php

declare(strict_types=1);

/*
 * This file is part of the Cursor Pagination package.
 *
 * (c) SILARHI <dev@silarhi.fr>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Silarhi\CursorPagination\Tests;

use Override;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Counts the SQL queries executed through Doctrine's DBAL logging middleware.
 */
final class QueryCounter extends AbstractLogger
{
    public int $count = 0;

    /**
     * $message is left untyped to stay compatible with psr/log 1.x.
     *
     * @param string|Stringable $message
     * @param mixed[]           $context
     */
    #[Override]
    public function log($level, $message, array $context = []): void
    {
        if (str_starts_with((string) $message, 'Executing ')) {
            ++$this->count;
        }
    }
}
