<?php

declare(strict_types=1);

/*
 * This file is part of the Cursor Pagination package.
 *
 * (c) SILARHI <dev@silarhi.fr>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Silarhi\CursorPagination\Tests\Configuration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silarhi\CursorPagination\Configuration\OrderConfiguration;

final class OrderConfigurationTest extends TestCase
{
    public function testDefaults(): void
    {
        $fieldValueGetter = static fn (): int => 42;

        $configuration = new OrderConfiguration('u.id', $fieldValueGetter);

        self::assertSame('u.id', $configuration->getFieldName());
        self::assertSame($fieldValueGetter, $configuration->getFieldValueGetter());
        self::assertTrue($configuration->isOrderAscending());
        self::assertSame('ASC', $configuration->getOrderByExpression());
        self::assertNull($configuration->isUnique());
    }

    #[DataProvider('provideDirections')]
    public function testDirection(bool $orderAscending, string $expectedOrderByExpression): void
    {
        $configuration = new OrderConfiguration('u.number', static fn (): int => 1, $orderAscending);

        self::assertSame($orderAscending, $configuration->isOrderAscending());
        self::assertSame($expectedOrderByExpression, $configuration->getOrderByExpression());
    }

    /**
     * @return iterable<string, array{bool, string}>
     */
    public static function provideDirections(): iterable
    {
        yield 'ascending' => [true, 'ASC'];
        yield 'descending' => [false, 'DESC'];
    }

    #[DataProvider('provideIsUnique')]
    public function testIsUnique(?bool $isUnique): void
    {
        $configuration = new OrderConfiguration('u.tenantId', static fn (): int => 1, isUnique: $isUnique);

        self::assertSame($isUnique, $configuration->isUnique());
    }

    /**
     * @return iterable<string, array{?bool}>
     */
    public static function provideIsUnique(): iterable
    {
        yield 'not declared' => [null];
        yield 'unique' => [true];
        yield 'non-unique' => [false];
    }
}
