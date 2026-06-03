<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

class Project extends Model
{
    use HasFactory;

    public const STATUSES = ['draft', 'submitted', 'in_review', 'approved', 'installed', 'closed', 'rejected'];

    protected static function booted(): void
    {
        // DB cascade deletes child document rows without firing model events,
        // so delete their files here before the project (and its application) go.
        static::deleting(function (Project $project) {
            $paths = $project->documents()->pluck('file_path');

            if ($application = $project->application) {
                $paths = $paths->merge($application->documents()->pluck('file_path'));
            }

            if ($paths->isNotEmpty()) {
                Storage::disk(Document::DISK)->delete($paths->all());
            }
        });
    }

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

    /**
     * Limit the query to projects the given user is allowed to see:
     * admins see everything; contractors and customers see their own.
     */
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

            // A user with no contractor/customer profile sees nothing.
            if (! $matched) {
                $q->whereRaw('1 = 0');
            }
        });
    }
}
