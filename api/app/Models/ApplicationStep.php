<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'step_key',
        'data',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(IncentiveApplication::class, 'application_id');
    }
}
