<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait HandlesIndexQueries
{
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
