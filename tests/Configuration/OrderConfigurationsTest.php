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

use function count;

use PHPUnit\Framework\TestCase;
use Silarhi\CursorPagination\Configuration\OrderConfiguration;
use Silarhi\CursorPagination\Configuration\OrderConfigurations;

final class OrderConfigurationsTest extends TestCase
{
    public function testConstructorKeepsConfigurationsInOrder(): void
    {
        $id = $this->createOrderConfiguration('u.id');
        $number = $this->createOrderConfiguration('u.number');

        $configurations = new OrderConfigurations($id, $number);

        self::assertCount(2, $configurations);
        self::assertSame([$id, $number], $configurations->getOrderConfigurations());
        self::assertSame([0 => $id, 1 => $number], iterator_to_array($configurations));
    }

    public function testEmpty(): void
    {
        $configurations = new OrderConfigurations();

        self::assertCount(0, $configurations);
        self::assertSame([], $configurations->getOrderConfigurations());
        self::assertSame([], iterator_to_array($configurations));
        self::assertFalse(isset($configurations[0]));
    }

    public function testAdd(): void
    {
        $id = $this->createOrderConfiguration('u.id');
        $number = $this->createOrderConfiguration('u.number');

        $configurations = new OrderConfigurations($id);
        $configurations->add($number);

        self::assertCount(2, $configurations);
        self::assertSame([$id, $number], $configurations->getOrderConfigurations());
    }

    public function testRemoveReindexesRemainingConfigurations(): void
    {
        $id = $this->createOrderConfiguration('u.id');
        $number = $this->createOrderConfiguration('u.number');
        $tenantId = $this->createOrderConfiguration('u.tenantId');

        $configurations = new OrderConfigurations($id, $number, $tenantId);
        $configurations->remove($number);

        self::assertCount(2, $configurations);
        self::assertSame([0 => $id, 1 => $tenantId], $configurations->getOrderConfigurations());
    }

    public function testRemoveComparesByIdentity(): void
    {
        $id = $this->createOrderConfiguration('u.id');
        $configurations = new OrderConfigurations($id);

        // Same field name, but a different instance: nothing must be removed
        $configurations->remove($this->createOrderConfiguration('u.id'));

        self::assertSame([$id], $configurations->getOrderConfigurations());
    }

    public function testClear(): void
    {
        $configurations = new OrderConfigurations(
            $this->createOrderConfiguration('u.id'),
            $this->createOrderConfiguration('u.number'),
        );
        $configurations->clear();

        self::assertCount(0, $configurations);
        self::assertSame([], $configurations->getOrderConfigurations());
    }

    public function testOffsetExistsAndOffsetGet(): void
    {
        $id = $this->createOrderConfiguration('u.id');
        $number = $this->createOrderConfiguration('u.number');

        $configurations = new OrderConfigurations($id, $number);

        self::assertTrue(isset($configurations[0]));
        self::assertTrue(isset($configurations[1]));
        self::assertFalse(isset($configurations[2]));
        self::assertSame($id, $configurations[0]);
        self::assertSame($number, $configurations[1]);
    }

    public function testOffsetSet(): void
    {
        $id = $this->createOrderConfiguration('u.id');
        $number = $this->createOrderConfiguration('u.number');
        $tenantId = $this->createOrderConfiguration('u.tenantId');

        $configurations = new OrderConfigurations($id, $number);

        // Replace an existing offset
        $configurations[1] = $tenantId;
        self::assertCount(2, $configurations);
        self::assertSame($tenantId, $configurations[1]);

        // Set a new offset
        $configurations[2] = $number;
        self::assertCount(3, $configurations);
        self::assertSame($number, $configurations[2]);
        self::assertSame([$id, $tenantId, $number], iterator_to_array($configurations));
    }

    public function testOffsetUnset(): void
    {
        $id = $this->createOrderConfiguration('u.id');
        $number = $this->createOrderConfiguration('u.number');
        $tenantId = $this->createOrderConfiguration('u.tenantId');

        $configurations = new OrderConfigurations($id, $number, $tenantId);
        unset($configurations[0]);

        self::assertFalse(isset($configurations[0]));
        self::assertCount(2, $configurations);
        self::assertSame(2, count($configurations));
        self::assertSame([1 => $number, 2 => $tenantId], $configurations->getOrderConfigurations());
    }

    private function createOrderConfiguration(string $fieldName): OrderConfiguration
    {
        return new OrderConfiguration($fieldName, static fn (): string => $fieldName);
    }
}
