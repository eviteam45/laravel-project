<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait FiltersByStatusCsv
{
    public function scopeWhereStatusCsv(Builder $query, ?string $csv): Builder
    {
        return $query->when(
            filled($csv),
            fn (Builder $q) => $q->whereIn('status', array_filter(explode(',', (string) $csv))),
        );
    }
}
