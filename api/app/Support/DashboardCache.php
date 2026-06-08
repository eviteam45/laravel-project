<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class DashboardCache
{
    public static function key(int|string $userId): string
    {
        return "dashboard.stats.{$userId}";
    }

    /**
     * @param  iterable<int|string|null>  $userIds
     */
    public static function forget(iterable $userIds): void
    {
        foreach ($userIds as $id) {
            if ($id !== null) {
                Cache::forget(self::key($id));
            }
        }
    }
}
