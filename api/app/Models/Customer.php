<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'full_name',
        'address',
        'phone',
        'account_email',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function applications(): HasManyThrough
    {
        return $this->hasManyThrough(IncentiveApplication::class, Project::class);
    }

    public function scopeFilter(Builder $query, Request $request): Builder
    {
        return $query->when($request->filled('search'), function ($q) use ($request) {
            $term = '%'.$request->query('search').'%';
            $q->where(fn ($sub) => $sub->where('full_name', 'like', $term)->orWhere('account_email', 'like', $term));
        });
    }

    public static function provision(array $data): self
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => 'customer',
            ]);

            return $user->customer()->create([
                'full_name' => $data['full_name'] ?? $data['name'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'account_email' => $data['email'],
            ]);
        });
    }

    public function deleteWithUser(): void
    {
        DB::transaction(function () {
            $userId = $this->user_id;
            $this->delete();
            User::whereKey($userId)->delete();
        });
    }
}
