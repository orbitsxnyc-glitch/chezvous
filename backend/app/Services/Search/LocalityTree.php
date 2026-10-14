<?php

namespace App\Services\Search;

use Illuminate\Support\Facades\DB;

class LocalityTree
{
    /**
     * Identifiant d'une localité et de tous ses descendants.
     *
     * @return list<int>
     */
    public function withDescendants(int $localityId): array
    {
        $rows = DB::select(<<<'SQL'
            WITH RECURSIVE tree AS (
                SELECT id FROM localities WHERE id = ?
                UNION ALL
                SELECT l.id FROM localities l JOIN tree t ON l.parent_id = t.id
            ) SELECT id FROM tree
        SQL, [$localityId]);

        return array_map(fn ($r) => (int) $r->id, $rows);
    }
}
