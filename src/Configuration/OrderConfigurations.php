<?php

declare(strict_types=1);

/*
 * This file is part of the Cursor Pagination package.
 *
 * (c) SILARHI <dev@silarhi.fr>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Silarhi\CursorPagination\Configuration;

use ArrayAccess;
use ArrayIterator;

use function count;

use Countable;
use IteratorAggregate;
use Override;

/**
 * @implements IteratorAggregate<int, OrderConfiguration>
 * @implements ArrayAccess<int, OrderConfiguration>
 */
final class OrderConfigurations implements IteratorAggregate, Countable, ArrayAccess
{
    /** @var array<int|string, OrderConfiguration> */
    private array $orderConfigurations;

    public function __construct(OrderConfiguration ...$orderConfigurations)
    {
        $this->orderConfigurations = $orderConfigurations;
    }

    public function add(OrderConfiguration $orderConfiguration): void
    {
        $this->orderConfigurations[] = $orderConfiguration;
    }

    public function remove(OrderConfiguration $orderConfiguration): void
    {
        $this->orderConfigurations = array_values(array_filter($this->orderConfigurations, static fn (OrderConfiguration $oc) => $oc !== $orderConfiguration));
    }

    public function clear(): void
    {
        $this->orderConfigurations = [];
    }

    /**
     * @return array<int|string, OrderConfiguration>
     */
    public function getOrderConfigurations(): array
    {
        return $this->orderConfigurations;
    }

    /**
     * @return ArrayIterator<int|string, OrderConfiguration>
     */
    #[Override]
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->orderConfigurations);
    }

    #[Override]
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->orderConfigurations[$offset]);
    }

    #[Override]
    public function offsetGet(mixed $offset): OrderConfiguration
    {
        return $this->orderConfigurations[$offset];
    }

    #[Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->orderConfigurations[$offset] = $value;
    }

    #[Override]
    public function offsetUnset(mixed $offset): void
    {
        unset($this->orderConfigurations[$offset]);
    }

    #[Override]
    public function count(): int
    {
        return count($this->orderConfigurations);
    }
}
