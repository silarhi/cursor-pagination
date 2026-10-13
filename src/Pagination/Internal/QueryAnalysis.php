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

/**
 * What CursorPagination needs to know about a DQL query, read from its AST by {@see QueryAnalysisWalker}.
 *
 * @internal
 */
final readonly class QueryAnalysis
{
    public function __construct(
        /** The query selects no entity: only scalar values (or DTOs built with NEW) */
        public bool $scalarOnly,
        /** A collection (to-many association) is joined: a root entity may take several SQL rows */
        public bool $joinsCollection,
        /** An arbitrary join (JOIN Entity e ON …) or several FROM entities: rows may be multiplied */
        public bool $joinsArbitrarily,
        /** SELECT DISTINCT on the root entity alone: the joined rows collapse into one row per root entity */
        public bool $distinctRoot,
        /** GROUP BY or HAVING */
        public bool $grouped,
    ) {
    }

    /**
     * Whether the query may return several SQL rows for one root entity.
     */
    public function multipliesRows(): bool
    {
        return $this->joinsCollection || $this->joinsArbitrarily;
    }

    /**
     * Whether a LIMIT on the SQL rows may cut a page short of root entities: hydration merges the rows of a root
     * entity into one result.
     */
    public function hydratesDuplicateRows(): bool
    {
        return !$this->scalarOnly && !$this->distinctRoot && $this->multipliesRows();
    }
}
