<?php

use App\Http\Controllers\Api\ApplicationStepController;
use App\Http\Controllers\Api\ApplicationTransitionController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\ContractorController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\IncentiveApplicationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectTransitionController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Public authentication endpoints — rate limited to slow credential stuffing.
Route::middleware('throttle:6,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Routes that require a valid Sanctum API token.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Epic 3 — Projects + Applications CRUD.
    Route::apiResource('projects', ProjectController::class);
    Route::post('projects/{project}/transition', [ProjectTransitionController::class, 'store']);
    Route::apiResource('applications', IncentiveApplicationController::class)
        ->only(['index', 'store', 'show', 'destroy']);

    // Epic 4 — Multi-step form: persist a step, upload docs, submit.
    Route::put('applications/{application}/steps/{stepKey}', [ApplicationStepController::class, 'update']);
    Route::post('applications/{application}/submit', [IncentiveApplicationController::class, 'submit']);
    Route::post('applications/{application}/documents', [DocumentController::class, 'storeForApplication']);
    Route::post('projects/{project}/documents', [DocumentController::class, 'storeForProject']);

    // Epic 5 — reviewer status transitions (audit + queued notification/payment).
    Route::post('applications/{application}/transition', [ApplicationTransitionController::class, 'store']);
    Route::delete('documents/{document}', [DocumentController::class, 'destroy']);

    // Picker lookups — must precede the resource routes.
    Route::get('customers/options', [CustomerController::class, 'options']);
    Route::get('contractors/options', [ContractorController::class, 'options']);

    // Admin-only contractor/customer management.
    Route::apiResource('contractors', ContractorController::class);
    Route::apiResource('customers', CustomerController::class);

    // Admin-only audit log viewer.
    Route::get('audit-logs', [AuditLogController::class, 'index']);

    // Epic 6 — dashboard stats + notifications.
    Route::get('dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);
});

// Private document download — secured by a temporary signed URL, not Sanctum,
// so the link works directly in the browser / <a href>.
Route::get('documents/{document}/download', [DocumentController::class, 'download'])
    ->name('documents.download')
    ->middleware('signed');
