<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class Contractor extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_name',
        'license_no',
        'phone',
        'region',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function scopeFilter(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->filled('search'), fn ($q) => $q->where('company_name', 'like', '%'.$request->query('search').'%'))
            ->when($request->filled('region'), fn ($q) => $q->where('region', $request->query('region')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')));
    }

    public static function provision(array $data): self
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => 'contractor',
            ]);

            return $user->contractor()->create([
                'company_name' => $data['company_name'],
                'license_no' => $data['license_no'] ?? null,
                'phone' => $data['phone'] ?? null,
                'region' => $data['region'] ?? null,
                'status' => $data['status'] ?? 'active',
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
