<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function contractor(): HasOne
    {
        return $this->hasOne(Contractor::class);
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function uploadedDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'uploaded_by');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function isContractor(): bool
    {
        return $this->role === 'contractor';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function scopeFilter(Builder $query, Request $request): Builder
    {
        return $query
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->query('search').'%';
                $q->where(fn ($s) => $s->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->query('role')));
    }

    public function changeRole(string $role): void
    {
        DB::transaction(function () use ($role) {
            $this->update(['role' => $role]);

            if ($role === 'contractor' && ! $this->contractor) {
                $this->contractor()->create([
                    'company_name' => $this->name,
                    'status' => 'active',
                ]);
            } elseif ($role === 'customer' && ! $this->customer) {
                $this->customer()->create([
                    'full_name' => $this->name,
                    'account_email' => $this->email,
                ]);
            }
        });
    }
}
