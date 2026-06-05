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
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:6,1');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::apiResource('projects', ProjectController::class);
    Route::post('projects/{project}/transition', [ProjectTransitionController::class, 'store']);
    Route::apiResource('applications', IncentiveApplicationController::class)
        ->only(['index', 'store', 'show', 'destroy']);

    Route::put('applications/{application}/steps/{stepKey}', [ApplicationStepController::class, 'update']);
    Route::post('applications/{application}/submit', [IncentiveApplicationController::class, 'submit']);
    Route::post('applications/{application}/documents', [DocumentController::class, 'storeForApplication']);
    Route::post('projects/{project}/documents', [DocumentController::class, 'storeForProject']);

    Route::post('applications/{application}/transition', [ApplicationTransitionController::class, 'store']);
    Route::delete('documents/{document}', [DocumentController::class, 'destroy']);

    Route::get('customers/options', [CustomerController::class, 'options']);
    Route::get('contractors/options', [ContractorController::class, 'options']);

    Route::apiResource('contractors', ContractorController::class);
    Route::apiResource('customers', CustomerController::class);

    Route::get('users', [UserController::class, 'index']);
    Route::patch('users/{user}/role', [UserController::class, 'updateRole']);

    Route::get('audit-logs', [AuditLogController::class, 'index']);

    Route::get('dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);
});

Route::get('documents/{document}/download', [DocumentController::class, 'download'])
    ->name('documents.download')
    ->middleware('signed');
