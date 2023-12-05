<?php

declare(strict_types=1);

/*
 * This file is part of the CFONB Parser package.
 *
 * (c) SILARHI <dev@silarhi.fr>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Silarhi\CursorPagination\Configuration;

use Closure;
use Doctrine\Common\Collections\Criteria;

class OrderConfiguration
{
    public function __construct(
        private string $fieldName,
        private Closure $fieldValueGetter,
        private bool $orderAscending = true,
        private ?bool $isUnique = null,
    ) {
    }

    public function isUnique(): ?bool
    {
        return $this->isUnique;
    }

    public function isOrderAscending(): bool
    {
        return $this->orderAscending;
    }

    public function getOrderByExpression(): string
    {
        return $this->orderAscending ? Criteria::ASC : Criteria::DESC;
    }

    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    public function getFieldValueGetter(): Closure
    {
        return $this->fieldValueGetter;
    }
}
