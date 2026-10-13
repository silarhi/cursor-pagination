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
use Doctrine\ORM\Tools\Pagination\CountWalker;
use Doctrine\ORM\Tools\Pagination\CursorPaginator;
use Generator;
use IteratorAggregate;
use LogicException;
use Override;
use ReflectionMethod;
use Silarhi\CursorPagination\Adapter\DoctrineCursorPaginatorAdapter;
use Silarhi\CursorPagination\Adapter\DoctrinePaginatorAdapter;
use Silarhi\CursorPagination\Adapter\PaginatorAdapterInterface;
use Silarhi\CursorPagination\Configuration\OrderConfiguration;
use Silarhi\CursorPagination\Configuration\OrderConfigurations;
use Silarhi\CursorPagination\Iterator\ChunkIterator;
use Silarhi\CursorPagination\Pagination\Internal\QueryAnalysis;
use Silarhi\CursorPagination\Pagination\Internal\QueryAnalysisWalker;
use SortDirection;

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

    private readonly PaginatorAdapterInterface $adapter;

    public function __construct(
        private readonly QueryBuilder $queryBuilder,
        private readonly OrderConfigurations $orderConfigurations,
        private readonly int $maxPerPages = 100,
        /**
         * Whether $maxPerPages counts root entities instead of SQL rows, with two queries per page: the page's
         * distinct ids, then their rows. null (auto) enables it when the query joins a collection or another entity
         * with JOIN … ON, unless it only selects scalar values. Never pass false on a query that joins a
         * collection: its pages would come back short, and the iteration would stop at the first one.
         */
        private readonly ?bool $fetchJoinCollection = null,
        /**
         * Whether Doctrine's output walkers build the derived queries (count, distinct ids). null picks them
         * for each query.
         */
        private readonly ?bool $useOutputWalkers = null,
        /*
         * Runs the page and count queries. null picks Doctrine's CursorPaginator when it exists (doctrine/orm 3.7+),
         * its Paginator otherwise.
         */
        ?PaginatorAdapterInterface $adapter = null,
    ) {
        $this->adapter = $adapter ?? (class_exists(CursorPaginator::class)
            ? new DoctrineCursorPaginatorAdapter()
            : new DoctrinePaginatorAdapter());
    }

    /**
     * @throws LogicException when the query builder has a first result
     */
    public function getNbPages(): int
    {
        return 0 >= $this->maxPerPages
            ? 0
            : (int) ceil($this->count() / $this->maxPerPages);
    }

    /**
     * @return Generator<int, T>
     *
     * @throws LogicException see getResults()
     */
    #[Override]
    public function getIterator(): Generator
    {
        return $this->getResults();
    }

    /**
     * The number of results: distinct root entities, or rows for a query that only selects scalar values.
     *
     * @throws LogicException when the query builder has a first result
     */
    #[Override]
    public function count(): int
    {
        if (null === $this->nbResults) {
            $queryBuilder = $this->cloneQueryBuilder();
            $analysis = QueryAnalysisWalker::analyse($queryBuilder);

            if ($analysis->scalarOnly) {
                // Every row is a result. Output walkers need the root identifier in the results, except to count
                // the groups of a grouped query
                $distinct = false;
                $useOutputWalkers = $this->useOutputWalkers ?? $analysis->grouped;
            } else {
                // Without joined rows to collapse, a plain COUNT(*) gives the same number
                $distinct = $analysis->multipliesRows() || $analysis->grouped;
                $useOutputWalkers = $this->useOutputWalkers ?? ($distinct ? null : false);
            }

            $query = $queryBuilder->getQuery()->setHint(CountWalker::HINT_DISTINCT, $distinct);

            $this->nbResults = max(0, $this->adapter->count($query, $useOutputWalkers));
        }

        return $this->nbResults;
    }

    /**
     * @return iterable<int, array<T>>
     *
     * @throws LogicException see getResults()
     */
    public function getChunkResults(): iterable
    {
        return (new ChunkIterator($this->getResults(), $this->maxPerPages))->getIterator();
    }

    /**
     * @return Generator<int, T>
     *
     * @throws LogicException before the first page when the order configurations are empty or all declared
     *                        isUnique: false, or when the query builder has a first result
     */
    public function getResults(): Generator
    {
        $this->validateOrderConfigurations();

        $baseQueryBuilder = $this->cloneQueryBuilder();
        $baseQueryBuilder->resetDQLPart('orderBy');
        // Each page is limited by the adapter
        $baseQueryBuilder->setMaxResults(null);

        foreach ($this->orderConfigurations as $index => $configuration) {
            $orderBy = $configuration->getFieldName();
            if (1 !== preg_match('/^[a-z_][a-z0-9_]*\.[a-z_][a-z0-9_]*$/i', $orderBy)) {
                // Doctrine's CursorPaginator only orders by fields and result variables: select any other expression
                // (IDENTITY(p.author), a function…) as a hidden result variable. The cursor condition keeps the
                // expression, a WHERE clause cannot use result variables
                $orderBy = sprintf('cursor_order_%d', $index);
                $baseQueryBuilder->addSelect(sprintf('%s AS HIDDEN %s', $configuration->getFieldName(), $orderBy));
            }

            $baseQueryBuilder->addOrderBy($orderBy, $this->sortDirection($configuration));
        }

        $analysis = QueryAnalysisWalker::analyse($baseQueryBuilder);
        $fetchJoinCollection = $this->resolveFetchJoinCollection($analysis);

        // The cursor is local to this generator so that every iteration starts from the beginning,
        // even if a previous one was stopped early, and concurrent iterations do not share it
        $afterValues = [];
        while (true) {
            $queryBuilder = clone $baseQueryBuilder;

            if ([] !== $afterValues) {
                $this->applyCursor($queryBuilder, $afterValues);
            }

            $page = $this->adapter->fetchPage($queryBuilder->getQuery(), $this->maxPerPages, $fetchJoinCollection, $this->useOutputWalkers);
            $results = $page->items;
            if ([] === $results) {
                break;
            }

            $isLastPage = !$page->hasMore;

            // Update cursor value before actually yielding results in order to avoid data loss
            $lastResult = $results[array_key_last($results)];
            $afterValues = $this->getCursorValues($lastResult);

            foreach ($results as $result) {
                yield $result;
            }

            if ($isLastPage) {
                break;
            }
        }
    }

    /**
     * doctrine/orm 3.7 deprecates 'ASC' and 'DESC' for the PHP 8.6 SortDirection enum (polyfilled by
     * symfony/polyfill-php86), doctrine/orm 4 requires it, older versions only accept strings.
     */
    private function sortDirection(OrderConfiguration $configuration): SortDirection|string
    {
        static $acceptsEnum = null;
        $acceptsEnum ??= str_contains((string) (new ReflectionMethod(QueryBuilder::class, 'addOrderBy'))->getParameters()[1]->getType(), 'SortDirection');

        if (!$acceptsEnum) {
            return $configuration->getOrderByExpression();
        }

        return $configuration->isOrderAscending() ? SortDirection::Ascending : SortDirection::Descending;
    }

    private function cloneQueryBuilder(): QueryBuilder
    {
        if (0 !== $this->queryBuilder->getFirstResult()) {
            throw new LogicException('The query builder must not have a first result (setFirstResult()): it would skip rows on every page. Filter the query instead.');
        }

        return clone $this->queryBuilder;
    }

    private function validateOrderConfigurations(): void
    {
        if (0 === count($this->orderConfigurations)) {
            throw new LogicException('At least one order configuration is required.');
        }

        foreach ($this->orderConfigurations as $configuration) {
            if (false !== $configuration->isUnique()) {
                return;
            }
        }

        throw new LogicException(1 === count($this->orderConfigurations) ? 'When using a single order configuration, it must be unique' : 'The order configurations are all declared non-unique: end them with a unique field, such as the id.');
    }

    private function resolveFetchJoinCollection(QueryAnalysis $analysis): bool
    {
        return $this->fetchJoinCollection ?? $analysis->hydratesDuplicateRows();
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

    /**
     * @return array<int, mixed>
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
