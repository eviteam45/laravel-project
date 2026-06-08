<?php

namespace App\Enums\Concerns;

trait HasValues
{
    /**
     * The backing values of every case — handy for validation rules and
     * whereIn() clauses that still operate on the raw string column.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
