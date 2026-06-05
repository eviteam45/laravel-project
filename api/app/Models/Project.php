<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['draft', 'submitted', 'in_review', 'approved', 'installed', 'closed', 'rejected'];

    public const SORTABLE = ['name', 'status', 'capacity_kw', 'install_date', 'created_at'];

    public const LOCKED_APPLICATION_STATUSES = ['submitted', 'under_review', 'reserved', 'paid'];

    protected $fillable = [
        'name',
        'contractor_id',
        'customer_id',
        'status',
        'address',
        'capacity_kw',
        'install_date',
    ];

    protected function casts(): array
    {
        return [
            'capacity_kw' => 'decimal:2',
            'install_date' => 'date',
        ];
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function batterySystems(): HasMany
    {
        return $this->hasMany(BatterySystem::class);
    }

    public function application(): HasOne
    {
        return $this->hasOne(IncentiveApplication::class);
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable');
    }

    public function scopeFilter(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->filled('status'), fn ($q) => $q->whereIn('status', array_filter(explode(',', (string) $request->query('status')))))
            ->when($request->filled('contractor_id'), fn ($q) => $q->where('contractor_id', $request->integer('contractor_id')))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('region'), fn ($q) => $q->whereHas('contractor', fn ($c) => $c->where('region', $request->query('region'))))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->query('search').'%';
                $q->where(fn ($sub) => $sub->where('name', 'like', $term)->orWhere('address', 'like', $term));
            })
            ->when($request->filled('min_capacity'), fn ($q) => $q->where('capacity_kw', '>=', $request->float('min_capacity')))
            ->when($request->filled('max_capacity'), fn ($q) => $q->where('capacity_kw', '<=', $request->float('max_capacity')))
            ->when($request->filled('install_from'), fn ($q) => $q->whereDate('install_date', '>=', $request->date('install_from')))
            ->when($request->filled('install_to'), fn ($q) => $q->whereDate('install_date', '<=', $request->date('install_to')))
            ->when($request->has('has_application'), fn ($q) => $request->boolean('has_application')
                ? $q->whereHas('application')
                : $q->whereDoesntHave('application'));
    }

    public function hasLockedApplication(): bool
    {
        return $this->application()->whereIn('status', self::LOCKED_APPLICATION_STATUSES)->exists();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $matched = false;

            if ($user->contractor) {
                $q->orWhere('contractor_id', $user->contractor->id);
                $matched = true;
            }

            if ($user->customer) {
                $q->orWhere('customer_id', $user->customer->id);
                $matched = true;
            }

            if (! $matched) {
                $q->whereRaw('1 = 0');
            }
        });
    }
}
