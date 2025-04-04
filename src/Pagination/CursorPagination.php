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

use Countable;
use Doctrine\ORM\Query\Expr\Andx;
use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Generator;
use IteratorAggregate;
use LogicException;
use Silarhi\CursorPagination\Configuration\OrderConfigurations;
use Silarhi\CursorPagination\Iterator\ChunkIterator;

use function sprintf;

/**
 * @template-covariant T
 *
 * @implements IteratorAggregate<int, T>
 */
final class CursorPagination implements IteratorAggregate, Countable
{
    /** @var array<int|string, mixed> */
    private array $afterValues = [];

    /** @var int<0, max>|null */
    private ?int $nbResults = null;

    public function __construct(
        private readonly QueryBuilder $queryBuilder,
        private readonly OrderConfigurations $orderConfigurations,
        private readonly int $maxPerPages = 100,
        private readonly bool $fetchJoinCollection = true,
        private readonly ?bool $useOutputWalkers = null,
    ) {
    }

    public function getNbPages(): int
    {
        return 0 >= $this->maxPerPages
            ? 0
            : (int) ceil($this->count() / $this->maxPerPages);
    }

    public function getIterator(): Generator
    {
        return $this->getResults();
    }

    public function count(): int
    {
        if (null === $this->nbResults) {
            $paginator = new Paginator($this->queryBuilder, $this->fetchJoinCollection);
            $paginator->setUseOutputWalkers($this->useOutputWalkers);

            $this->nbResults = $paginator->count();
        }

        return $this->nbResults;
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

        $this->resetCursorValues();
    }

    private function applyCursor(QueryBuilder $queryBuilder): void
    {
        $whereClause = new Orx();
        $previousConditions = new Andx();
        foreach ($this->orderConfigurations as $index => $configuration) {
            $useLargerThan = $configuration->isOrderAscending();
            $sign = $useLargerThan ? '>' : '<';

            if (false === $configuration->isUnique() && 1 === count($this->orderConfigurations)) {
                throw new LogicException('When using a single order configuration, it must be unique');
            }

            $cursorParameterName = sprintf(':cursor_parameter_%d', $index);
            $currentCondition = clone $previousConditions;
            $currentCondition->add(new Comparison(
                $configuration->getFieldName(),
                $sign,
                $cursorParameterName,
            ));

            $queryBuilder->setParameter($cursorParameterName, $this->afterValues[$index]);

            $whereClause->add($currentCondition);

            $previousConditions->add(new Comparison(
                $configuration->getFieldName(),
                '=',
                $cursorParameterName,
            ));
        }

        $queryBuilder->andWhere($whereClause);
    }

    private function resetCursorValues(): void
    {
        $this->afterValues = [];
    }

    private function updateCursorValues(mixed $item): void
    {
        foreach ($this->orderConfigurations as $index => $orderConfiguration) {
            $valueGetter = $orderConfiguration->getFieldValueGetter();
            $this->afterValues[$index] = $valueGetter($item);
        }
    }
}
