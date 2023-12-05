<?php

declare(strict_types=1);

/*
 * This file is part of the CFONB Parser package.
 *
 * (c) SILARHI <dev@silarhi.fr>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Silarhi\CursorPagination\Tests\Iterator;

use function count;

use PHPUnit\Framework\TestCase;
use Silarhi\CursorPagination\Iterator\ChunkIterator;

class ChunkIteratorTest extends TestCase
{
    /**
     * @dataProvider provideChunk
     *
     * @param iterable<int, mixed> $data
     * @param array<int, mixed>    $expectedYields
     */
    public function testGetIterator(iterable $data, int $size, array $expectedYields): void
    {
        $iterator = new ChunkIterator($data, $size);
        $iterations = 0;
        foreach ($iterator->getIterator() as $i => $chunks) {
            self::assertArrayHasKey($i, $expectedYields);
            self::assertEquals(array_values($chunks), $expectedYields[$i]);
            ++$iterations;
        }
        self::assertEquals(count($expectedYields), $iterations);
    }

    /**
     * @return iterable<int, array{data: iterable<int, mixed>, size: int, expectedYields: array<int, array<int, mixed>>}>
     */
    public function provideChunk(): iterable
    {
        yield [
            'data' => [1, 2, 3, 4, 5],
            'size' => 2,
            'expectedYields' => [
                [1, 2],
                [3, 4],
                [5],
            ],
        ];

        $yield = fn () => yield from [1, 2, 3, 4, 5];
        yield [
            'data' => $yield(),
            'size' => 2,
            'expectedYields' => [
                [1, 2],
                [3, 4],
                [5],
            ],
        ];
    }
}
