<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Models\Concerns\FiltersByStatusCsv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;

class IncentiveApplication extends Model
{
    use FiltersByStatusCsv, HasFactory, SoftDeletes;

    public const SORTABLE = ['status', 'submitted_at', 'created_at', 'updated_at'];

    public const STEP_KEYS = ['eligibility', 'system', 'documents', 'banking', 'review'];

    protected $fillable = [
        'project_id',
        'current_step',
        'submitted_at',
        'incentive_amount',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'incentive_amount' => 'decimal:2',
            'status' => ApplicationStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::forceDeleted(function (IncentiveApplication $application) {
            $application->documents()->get()->each->delete();
            $application->notes()->delete();
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApplicationStep::class, 'application_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(IncentivePayment::class, 'application_id');
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
            ->whereStatusCsv($request->query('status'))
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->integer('project_id')))
            ->when($request->filled('contractor_id'), fn ($q) => $q->where('contractor_id', $request->integer('contractor_id')))
            ->when($request->filled('region'), fn ($q) => $q->whereHas('project.contractor', fn ($c) => $c->where('region', $request->query('region'))))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = (string) $request->query('search');
                $mysql = $q->getConnection()->getDriverName() === 'mysql';

                $q->whereHas('project', function ($p) use ($term, $mysql) {
                    if ($mysql) {
                        $p->whereFullText(['name', 'address'], $term)
                            ->orWhereHas('contractor', fn ($c) => $c->whereFullText('company_name', $term));
                    } else {
                        $like = '%'.$term.'%';
                        $p->where('name', 'like', $like)
                            ->orWhereHas('contractor', fn ($c) => $c->where('company_name', 'like', $like));
                    }
                });
            })
            ->when($request->filled('submitted_from'), fn ($q) => $q->whereDate('submitted_at', '>=', $request->date('submitted_from')))
            ->when($request->filled('submitted_to'), fn ($q) => $q->whereDate('submitted_at', '<=', $request->date('submitted_to')));
    }

    public function isLocked(): bool
    {
        return in_array($this->status, ApplicationStatus::locked(), true);
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

    public function completedStepKeys(): array
    {
        return $this->steps()->whereNotNull('completed_at')->pluck('step_key')->all();
    }

    public function missingStepKeys(): array
    {
        return array_values(array_diff(self::STEP_KEYS, $this->completedStepKeys()));
    }

    public function recomputeCurrentStep(): void
    {
        $missing = $this->missingStepKeys();
        $this->update(['current_step' => $missing[0] ?? null]);
    }
}
