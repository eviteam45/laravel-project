<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait HandlesIndexQueries
{
    /**
     * Cursor pagination for append-only lists — O(1) deep pages (no
     * COUNT(*)+OFFSET). The query must already carry a deterministic, unique
     * ordering for the cursor to be stable.
     */
    protected function cursorPaginated(
        Builder $query,
        Request $request,
        int $defaultPerPage = 25,
    ): CursorPaginator {
        $perPage = min(max((int) $request->query('per_page', $defaultPerPage), 1), 100);

        return $query->cursorPaginate($perPage)->withQueryString();
    }

    protected function paginated(
        Builder $query,
        Request $request,
        ?array $sortable = null,
        string $defaultSort = 'created_at',
        int $defaultPerPage = 15,
    ): LengthAwarePaginator {
        if ($sortable !== null) {
            $sort = in_array($request->query('sort'), $sortable, true) ? $request->query('sort') : $defaultSort;
            $dir = $request->query('dir') === 'asc' ? 'asc' : 'desc';
            $query->orderBy($sort, $dir)->orderBy($query->getModel()->getKeyName(), $dir);
        }

        $perPage = min(max((int) $request->query('per_page', $defaultPerPage), 1), 100);

        return $query->paginate($perPage)->withQueryString();
    }
}
