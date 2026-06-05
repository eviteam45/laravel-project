<?php

namespace App\Models;

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
    use HasFactory, SoftDeletes;

    public const STATUSES = ['started', 'in_progress', 'submitted', 'under_review', 'reserved', 'paid', 'rejected', 'withdrawn'];

    public const SORTABLE = ['status', 'submitted_at', 'created_at', 'updated_at'];

    public const LOCKED_STATUSES = ['submitted', 'under_review', 'reserved', 'paid'];

    public const STEP_KEYS = ['eligibility', 'system', 'documents', 'banking', 'review'];

    protected $fillable = [
        'project_id',
        'status',
        'current_step',
        'submitted_at',
        'incentive_amount',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'incentive_amount' => 'decimal:2',
        ];
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
            ->when($request->filled('status'), fn ($q) => $q->whereIn('status', array_filter(explode(',', (string) $request->query('status')))))
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->integer('project_id')))
            ->when($request->filled('contractor_id'), fn ($q) => $q->whereHas('project', fn ($p) => $p->where('contractor_id', $request->integer('contractor_id'))))
            ->when($request->filled('region'), fn ($q) => $q->whereHas('project.contractor', fn ($c) => $c->where('region', $request->query('region'))))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->query('search').'%';
                $q->whereHas('project', fn ($p) => $p->where('name', 'like', $term)
                    ->orWhereHas('contractor', fn ($c) => $c->where('company_name', 'like', $term)));
            })
            ->when($request->filled('submitted_from'), fn ($q) => $q->whereDate('submitted_at', '>=', $request->date('submitted_from')))
            ->when($request->filled('submitted_to'), fn ($q) => $q->whereDate('submitted_at', '<=', $request->date('submitted_to')));
    }

    public function isLocked(): bool
    {
        return in_array($this->status, self::LOCKED_STATUSES, true);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereHas('project', fn (Builder $q) => $q->visibleTo($user));
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
