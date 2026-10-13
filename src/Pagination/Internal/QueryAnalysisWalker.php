<?php

declare(strict_types=1);

/*
 * This file is part of the Cursor Pagination package.
 *
 * (c) SILARHI <dev@silarhi.fr>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Silarhi\CursorPagination\Pagination\Internal;

use function array_filter;
use function count;

use Doctrine\ORM\Query;
use Doctrine\ORM\Query\AST\GroupByClause;
use Doctrine\ORM\Query\AST\HavingClause;
use Doctrine\ORM\Query\AST\IdentificationVariableDeclaration;
use Doctrine\ORM\Query\AST\Join;
use Doctrine\ORM\Query\AST\JoinAssociationDeclaration;
use Doctrine\ORM\Query\AST\PartialObjectExpression;
use Doctrine\ORM\Query\AST\SelectExpression;
use Doctrine\ORM\Query\AST\SelectStatement;
use Doctrine\ORM\Query\TreeWalkerAdapter;
use Doctrine\ORM\QueryBuilder;

use function is_string;

use LogicException;
use Override;

use function reset;

/**
 * Reads the AST of a query, with its query components, and stores a {@see QueryAnalysis} in a query hint.
 * It does not change the query.
 *
 * @internal
 */
final class QueryAnalysisWalker extends TreeWalkerAdapter
{
    public const HINT_ANALYSIS = 'silarhi_cursor_pagination.analysis';

    /**
     * Parses the query of $queryBuilder, without executing it.
     */
    public static function analyse(QueryBuilder $queryBuilder): QueryAnalysis
    {
        $query = $queryBuilder->getQuery();
        $walkers = $query->getHint(Query::HINT_CUSTOM_TREE_WALKERS);
        $walkers = false === $walkers ? [] : (array) $walkers;
        $walkers[] = self::class;
        $query->setHint(Query::HINT_CUSTOM_TREE_WALKERS, $walkers);
        // A cached parser result would skip the walker
        $query->expireQueryCache();
        $query->getSQL();

        $analysis = $query->getHint(self::HINT_ANALYSIS);
        if (!$analysis instanceof QueryAnalysis) {
            throw new LogicException('CursorPagination only supports SELECT queries.');
        }

        return $analysis;
    }

    #[Override]
    public function walkSelectStatement(SelectStatement $selectStatement): void
    {
        $queryComponents = $this->getQueryComponents();
        $declarations = array_filter(
            $selectStatement->fromClause->identificationVariableDeclarations,
            static fn (mixed $declaration): bool => $declaration instanceof IdentificationVariableDeclaration,
        );
        $rootAlias = [] === $declarations ? null : reset($declarations)->rangeVariableDeclaration?->aliasIdentificationVariable;

        $scalarOnly = true;
        $selectsRootOnly = true;
        foreach ($selectStatement->selectClause->selectExpressions as $selectExpression) {
            if (!$selectExpression instanceof SelectExpression) {
                continue;
            }

            $expression = $selectExpression->expression;
            $alias = match (true) {
                is_string($expression) => $expression,
                // doctrine/orm 3.3+
                $expression instanceof PartialObjectExpression => $expression->identificationVariable,
                default => null,
            };

            if (null !== $alias && isset($queryComponents[$alias]['metadata']) && !isset($queryComponents[$alias]['resultVariable'])) {
                $scalarOnly = false;
                $selectsRootOnly = $selectsRootOnly && $alias === $rootAlias && !$selectExpression->hiddenAliasResultVariable;
            } else {
                $selectsRootOnly = false;
            }
        }

        $joinsCollection = false;
        $joinsArbitrarily = count($declarations) > 1;
        foreach ($declarations as $declaration) {
            foreach ($declaration->joins as $join) {
                if (!$join instanceof Join) {
                    continue;
                }

                $joinDeclaration = $join->joinAssociationDeclaration;
                if ($joinDeclaration instanceof JoinAssociationDeclaration) {
                    $relation = $queryComponents[$joinDeclaration->aliasIdentificationVariable]['relation'] ?? null;
                    $joinsCollection = $joinsCollection || true === $relation?->isToMany();
                } else {
                    // JOIN Entity e WITH …: nothing tells how many rows match
                    $joinsArbitrarily = true;
                }
            }
        }

        $this->_getQuery()->setHint(self::HINT_ANALYSIS, new QueryAnalysis(
            scalarOnly: $scalarOnly,
            joinsCollection: $joinsCollection,
            joinsArbitrarily: $joinsArbitrarily,
            distinctRoot: $selectStatement->selectClause->isDistinct && $selectsRootOnly,
            grouped: $selectStatement->groupByClause instanceof GroupByClause || $selectStatement->havingClause instanceof HavingClause,
        ));
    }
}
