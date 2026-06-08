<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserRoleManager
{
    public function changeRole(User $user, string $role): void
    {
        DB::transaction(function () use ($user, $role) {
            if ($role !== Role::Contractor->value && $user->contractor) {
                if ($user->contractor->projects()->exists()) {
                    throw ValidationException::withMessages([
                        'role' => ['This user has projects as a contractor; reassign or remove them before changing role.'],
                    ]);
                }
                $user->contractor->delete();
                $user->setRelation('contractor', null);
            }

            if ($role !== Role::Customer->value && $user->customer) {
                if ($user->customer->projects()->exists()) {
                    throw ValidationException::withMessages([
                        'role' => ['This user has projects as a customer; reassign or remove them before changing role.'],
                    ]);
                }
                $user->customer->delete();
                $user->setRelation('customer', null);
            }

            $user->update(['role' => $role]);

            if ($role === Role::Contractor->value && ! $user->contractor) {
                $user->contractor()->create([
                    'company_name' => $user->name,
                    'status' => 'active',
                ]);
            } elseif ($role === Role::Customer->value && ! $user->customer) {
                $user->customer()->create([
                    'full_name' => $user->name,
                    'account_email' => $user->email,
                ]);
            }
        });

        Cache::forget(TransitionNotifier::ADMIN_RECIPIENTS_CACHE_KEY);
    }
}
