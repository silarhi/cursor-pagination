<?php

declare(strict_types=1);

/*
 * This file is part of the Cursor Pagination package.
 *
 * (c) SILARHI <dev@silarhi.fr>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Silarhi\CursorPagination\Iterator;

use function count;

use IteratorAggregate;
use Traversable;

/**
 * @template TValue
 * @template TKey of int|string
 *
 * @implements IteratorAggregate<TKey, TValue>
 */
final readonly class ChunkIterator implements IteratorAggregate
{
    /**
     * @param iterable<TKey, TValue> $data
     */
    public function __construct(private iterable $data, private int $size)
    {
    }

    /**
     * @return Traversable<int, array<TKey, TValue>>
     */
    public function getIterator(): Traversable
    {
        $results = [];
        foreach ($this->data as $i => $result) {
            $results[$i] = $result;
            if (count($results) === $this->size) {
                yield $results;

                unset($results); // Call GC
                $results = [];
            }
        }

        // yield rest
        if ([] !== $results) {
            yield $results;
        }
    }
}
