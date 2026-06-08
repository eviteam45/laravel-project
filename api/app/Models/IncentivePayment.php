<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncentivePayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'application_id',
        'amount',
        'status',
        'scheduled_for',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'scheduled_for' => 'date',
            'paid_at' => 'datetime',
            'status' => PaymentStatus::class,
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(IncentiveApplication::class, 'application_id');
    }
}
