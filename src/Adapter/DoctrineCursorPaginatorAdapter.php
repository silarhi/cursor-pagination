<?php

declare(strict_types=1);

/*
 * This file is part of the Cursor Pagination package.
 *
 * (c) SILARHI <dev@silarhi.fr>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Silarhi\CursorPagination\Adapter;

use function array_key_exists;
use function array_sum;
use function current;

use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\Tools\Pagination\CountOutputWalker;
use Doctrine\ORM\Tools\Pagination\CountWalker;
use Doctrine\ORM\Tools\Pagination\CursorPaginator;

use function is_array;

use Override;

/**
 * Doctrine's CursorPaginator, for doctrine/orm 3.7 and later.
 *
 * It is called without a Doctrine cursor: CursorPagination already put its own cursor condition in the query, which
 * handles any ORDER BY expression and reads its values through the order configurations' closures. CursorPaginator limits the page, fetches one more row to tell
 * whether a next page exists, and runs the distinct-ids query of the fetch-join-collection strategy.
 *
 * CursorPaginator only counts the results of a page it fetched: the count query is built here with Doctrine's
 * count walkers, as it does.
 */
final class DoctrineCursorPaginatorAdapter implements PaginatorAdapterInterface
{
    #[Override]
    public function fetchPage(Query $query, int $maxResults, bool $fetchJoinCollection, ?bool $useOutputWalkers): PageResult
    {
        $page = (new CursorPaginator($maxResults, $fetchJoinCollection, $useOutputWalkers))->paginate($query);

        return new PageResult($page->getItems(), $page->hasNextPage());
    }

    #[Override]
    public function count(Query $query, ?bool $useOutputWalkers): int
    {
        $query
            ->setCacheable(false)
            ->setFirstResult(0)
            ->setMaxResults(null);
        if (!$query->hasHint(CountWalker::HINT_DISTINCT)) {
            $query->setHint(CountWalker::HINT_DISTINCT, true);
        }

        // null is Doctrine's default: output walkers, unless the query already has a custom output walker
        if ($useOutputWalkers ?? false === (bool) $query->getHint(Query::HINT_CUSTOM_OUTPUT_WALKER)) {
            $platform = $query->getEntityManager()->getConnection()->getDatabasePlatform();
            $column = match (true) {
                // The casing of unquoted column aliases in the results
                $platform instanceof DB2Platform, $platform instanceof OraclePlatform => 'DCTRN_COUNT',
                default => 'dctrn_count',
            };

            $resultSetMapping = new ResultSetMapping();
            $resultSetMapping->addScalarResult($column, 'count');

            $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, CountOutputWalker::class);
            $query->setResultSetMapping($resultSetMapping);
        } else {
            $walkers = $query->getHint(Query::HINT_CUSTOM_TREE_WALKERS);
            $walkers = false === $walkers ? [] : (array) $walkers;
            $walkers[] = CountWalker::class;
            $query->setHint(Query::HINT_CUSTOM_TREE_WALKERS, $walkers);

            // The select clause is replaced: parameters only used there would be bound without being used
            $parameterMappings = (new Parser($query))->parse()->getParameterMappings();
            $parameters = $query->getParameters();
            foreach ($parameters as $key => $parameter) {
                if (!array_key_exists($parameter->getName(), $parameterMappings)) {
                    $parameters->remove($key);
                }
            }
        }

        // Without output walkers, a grouped query returns one count per group
        $counts = [];
        foreach ($query->getScalarResult() as $row) {
            $counts[] = is_array($row) ? current($row) : $row;
        }

        return (int) array_sum($counts);
    }
}
