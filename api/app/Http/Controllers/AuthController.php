<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\Contractor;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a contractor or customer, create their profile, and issue a token.
     *
     * Self-registration cannot create admins — those are provisioned internally.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(['contractor', 'customer'])],
            'phone' => ['nullable', 'string', 'max:50'],
            // Contractor-only:
            'company_name' => [Rule::requiredIf(fn () => $request->input('role') === 'contractor'), 'string', 'max:255'],
            'license_no' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:255'],
            // Customer-only:
            'full_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'],
            ]);

            if ($validated['role'] === 'contractor') {
                $user->contractor()->create([
                    'company_name' => $validated['company_name'],
                    'license_no' => $validated['license_no'] ?? null,
                    'phone' => $validated['phone'] ?? null,
                    'region' => $validated['region'] ?? null,
                    'status' => 'active',
                ]);
            } else {
                $user->customer()->create([
                    'full_name' => $validated['full_name'] ?? $validated['name'],
                    'phone' => $validated['phone'] ?? null,
                    'address' => $validated['address'] ?? null,
                    'account_email' => $validated['email'],
                ]);
            }

            return $user;
        });

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ], 201);
    }

    /**
     * Authenticate a user and issue an API token.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    /**
     * Return the currently authenticated user (top-level, unwrapped, for the SPA).
     */
    public function user(Request $request): JsonResponse
    {
        return response()->json(new UserResource($request->user()));
    }

    /**
     * Revoke the token used for the current request.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
