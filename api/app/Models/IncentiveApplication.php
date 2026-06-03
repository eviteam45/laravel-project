<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

class IncentiveApplication extends Model
{
    use HasFactory;

    public const STATUSES = ['started', 'in_progress', 'submitted', 'under_review', 'reserved', 'paid', 'rejected', 'withdrawn'];

    protected static function booted(): void
    {
        // Clean up document files when an application is deleted directly
        // (project deletion is handled by the Project model).
        static::deleting(function (IncentiveApplication $application) {
            $paths = $application->documents()->pluck('file_path');

            if ($paths->isNotEmpty()) {
                Storage::disk(Document::DISK)->delete($paths->all());
            }
        });
    }

    /** Ordered keys of the multi-step application form. */
    public const STEP_KEYS = ['eligibility', 'system', 'documents', 'review'];

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

    /**
     * Limit to applications whose parent project is visible to the user.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereHas('project', fn (Builder $q) => $q->visibleTo($user));
    }

    /**
     * @return list<string> step keys that have been marked complete
     */
    public function completedStepKeys(): array
    {
        return $this->steps()->whereNotNull('completed_at')->pluck('step_key')->all();
    }

    /**
     * @return list<string> step keys still outstanding, in order
     */
    public function missingStepKeys(): array
    {
        return array_values(array_diff(self::STEP_KEYS, $this->completedStepKeys()));
    }

    /**
     * Point `current_step` at the first incomplete step (null when all done).
     */
    public function recomputeCurrentStep(): void
    {
        $missing = $this->missingStepKeys();
        $this->update(['current_step' => $missing[0] ?? null]);
    }
}
