<?php

declare(strict_types=1);

/*
 * This file is part of the Cursor Pagination package.
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
use Override;
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
    /** @var int<0, max>|null */
    private ?int $nbResults = null;

    public function __construct(
        private readonly QueryBuilder $queryBuilder,
        private readonly OrderConfigurations $orderConfigurations,
        private readonly int $maxPerPages = 100,
        /**
         * Doctrine Paginator's fetchJoinCollection flag. null (auto) enables it only when the query has joins
         * or several root entities, the only cases where root rows may be duplicated.
         */
        private readonly ?bool $fetchJoinCollection = null,
        private readonly ?bool $useOutputWalkers = null,
    ) {
    }

    public function getNbPages(): int
    {
        return 0 >= $this->maxPerPages
            ? 0
            : (int) ceil($this->count() / $this->maxPerPages);
    }

    #[Override]
    public function getIterator(): Generator
    {
        return $this->getResults();
    }

    #[Override]
    public function count(): int
    {
        if (null === $this->nbResults) {
            $paginator = new Paginator($this->queryBuilder, $this->resolveFetchJoinCollection());
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

        // The cursor is local to this generator so that every iteration starts from the beginning,
        // even if a previous one was stopped early, and concurrent iterations do not share it
        $afterValues = [];
        while (true) {
            $queryBuilder = clone $baseQueryBuilder;

            if ([] !== $afterValues) {
                $this->applyCursor($queryBuilder, $afterValues);
            }

            $paginator = new Paginator($queryBuilder, $this->resolveFetchJoinCollection());
            $paginator->setUseOutputWalkers($this->useOutputWalkers);

            $results = iterator_to_array($paginator->getIterator());
            if ([] === $results) {
                break;
            }

            // Update cursor value before actually yielding results in order to avoid data loss
            $lastResult = $results[array_key_last($results)];
            $afterValues = $this->getCursorValues($lastResult);

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

    /**
     * @param array<int|string, mixed> $afterValues
     */
    private function applyCursor(QueryBuilder $queryBuilder, array $afterValues): void
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

            $queryBuilder->setParameter($cursorParameterName, $afterValues[$index]);

            $whereClause->add($currentCondition);

            $previousConditions->add(new Comparison(
                $configuration->getFieldName(),
                '=',
                $cursorParameterName,
            ));
        }

        $configurations = $this->orderConfigurations->getOrderConfigurations();
        if (count($configurations) > 1) {
            // Redundant bound on the leading column: (a > x OR (a = x AND b > y)) implies a >= x. Unlike the OR
            // expansion, this plain range predicate lets the database seek an index on the leading column
            // instead of scanning it from the start on every page (e.g. PostgreSQL)
            $leadingIndex = array_key_first($configurations);
            $leading = $configurations[$leadingIndex];
            $queryBuilder->andWhere(new Comparison(
                $leading->getFieldName(),
                $leading->isOrderAscending() ? '>=' : '<=',
                sprintf(':cursor_parameter_%d', $leadingIndex),
            ));
        }

        $queryBuilder->andWhere($whereClause);
    }

    private function resolveFetchJoinCollection(): bool
    {
        return $this->fetchJoinCollection
            ?? ([] !== $this->queryBuilder->getDQLPart('join') || 1 !== count($this->queryBuilder->getRootAliases()));
    }

    /**
     * @return array<int|string, mixed>
     */
    private function getCursorValues(mixed $item): array
    {
        $afterValues = [];
        foreach ($this->orderConfigurations as $index => $orderConfiguration) {
            $valueGetter = $orderConfiguration->getFieldValueGetter();
            $afterValues[$index] = $valueGetter($item);
        }

        return $afterValues;
    }
}
