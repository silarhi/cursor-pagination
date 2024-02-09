<?php

declare(strict_types=1);

/*
 * This file is part of the CFONB Parser package.
 *
 * (c) SILARHI <dev@silarhi.fr>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Silarhi\CursorPagination\Pagination;

use function count;

use Doctrine\ORM\Query\Expr\Andx;
use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Generator;
use Silarhi\CursorPagination\Configuration\OrderConfigurations;
use Silarhi\CursorPagination\Iterator\ChunkIterator;

/**
 * @template-covariant T
 */
class CursorPagination
{
    /** @var array<int|string, mixed> */
    private array $afterValues = [];

    public function __construct(
        private QueryBuilder $queryBuilder,
        private OrderConfigurations $orderConfigurations,
        private int $maxPerPages = 100,
        private bool $fetchJoinCollection = true,
        private ?bool $useOutputWalkers = null,
    ) {
    }

    /**
     * @return iterable<int, array<T>>
     */
    public function getChunkResults(): iterable
    {
        return (new ChunkIterator($this->getResults(), $this->maxPerPages))->getIterator();
    }

    /**
     * @return Generator<int, T>
     */
    public function getResults(): Generator
    {
        $baseQueryBuilder = clone $this->queryBuilder;
        $baseQueryBuilder->resetDQLPart('orderBy');
        $baseQueryBuilder->setMaxResults($this->maxPerPages);

        foreach ($this->orderConfigurations as $configuration) {
            $baseQueryBuilder->addOrderBy(
                $configuration->getFieldName(),
                $configuration->getOrderByExpression(),
            );
        }

        while (true) {
            $queryBuilder = clone $baseQueryBuilder;

            if ([] !== $this->afterValues) {
                $this->applyCursor($queryBuilder);
            }

            $paginator = new Paginator($queryBuilder, $this->fetchJoinCollection);
            $paginator->setUseOutputWalkers($this->useOutputWalkers);

            $results = iterator_to_array($paginator->getIterator());
            if ([] === $results) {
                break;
            }

            // Update cursor value before actually yielding results in order to avoid data loss
            $lastResult = $results[array_key_last($results)];
            $this->updateCursorValues($lastResult);

            $yieldResults = 0;
            foreach ($results as $result) {
                ++$yieldResults;
                yield $result;
            }

            if ($yieldResults < $this->maxPerPages) {
                break;
            }
        }
    }

    private function applyCursor(QueryBuilder $queryBuilder): void
    {
        $whereClause = new Andx();
        foreach ($this->orderConfigurations as $index => $configuration) {
            $useLargerThan = $configuration->isOrderAscending();
            $sign = $useLargerThan ? '>' : '<';
            $isUnique = $configuration->isUnique()
                ?? (
                    1 === count($this->orderConfigurations)
                    || $index === count($this->orderConfigurations) - 1
                );

            if (!$isUnique) {
                $sign .= '=';
            }

            $cursorParameterName = sprintf(':cursor_parameter_%d', $index);
            $whereClause->add(new Comparison(
                $configuration->getFieldName(),
                $sign,
                $cursorParameterName,
            ));

            $queryBuilder->setParameter($cursorParameterName, $this->afterValues[$index]);
        }

        $queryBuilder->andWhere($whereClause);
    }

    private function updateCursorValues(mixed $item): void
    {
        foreach ($this->orderConfigurations as $index => $orderConfiguration) {
            $valueGetter = $orderConfiguration->getFieldValueGetter();
            $this->afterValues[$index] = $valueGetter($item);
        }
    }
}
