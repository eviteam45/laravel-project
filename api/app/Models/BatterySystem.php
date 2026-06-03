<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatterySystem extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'oem',
        'model',
        'quantity',
        'usable_capacity_kwh',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'usable_capacity_kwh' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
