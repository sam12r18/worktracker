<?php

use App\Http\Controllers\Api\V1\ActivitySessionController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\OperationsController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SyncConflictController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\WorkEventController;
use App\Http\Middleware\RequireWorkTrackerHttps;
use App\Http\Middleware\RequireWorkTrackerTokenAbility;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', RequireWorkTrackerHttps::class])->group(function () {
    // Device bootstrap and sync. Device tokens cannot read reports or management APIs.
    Route::post('devices', [DeviceController::class, 'store'])
        ->middleware([RequireWorkTrackerTokenAbility::class . ':device:register,admin:write', 'throttle:10,1']);
    Route::post('sync', SyncController::class)
        ->middleware([RequireWorkTrackerTokenAbility::class . ':device:sync,admin:write', 'throttle:120,1']);

    // Administrative read API.
    Route::middleware([RequireWorkTrackerTokenAbility::class . ':admin:read,admin:write', 'throttle:120,1'])->group(function () {
        Route::get('devices', [DeviceController::class, 'index']);
        Route::get('devices/{device}', [DeviceController::class, 'show']);
        Route::get('projects', [ProjectController::class, 'index']);
        Route::get('projects/{project}', [ProjectController::class, 'show']);
        Route::get('customers', [CustomerController::class, 'index']);
        Route::get('customers/{customer}', [CustomerController::class, 'show']);
        Route::get('projects/{project}/tasks', [TaskController::class, 'index']);
        Route::get('projects/{project}/tasks/{task}', [TaskController::class, 'show']);
        Route::get('activity-sessions', [ActivitySessionController::class, 'index']);
        Route::get('activity-sessions/{activitySession}', [ActivitySessionController::class, 'show']);
        Route::get('reports/daily', [ReportController::class, 'daily']);
        Route::get('reports/projects/{project}', [ReportController::class, 'project']);
        Route::get('work-events', [WorkEventController::class, 'index']);
        Route::get('operations/overview', [OperationsController::class, 'overview']);
        Route::get('sync-conflicts', [SyncConflictController::class, 'index']);
        Route::get('sync-conflicts/{syncConflict}', [SyncConflictController::class, 'show']);
    });

    // Administrative mutations require a write-scoped token.
    Route::middleware([RequireWorkTrackerTokenAbility::class . ':admin:write', 'throttle:60,1'])->group(function () {
        Route::post('projects', [ProjectController::class, 'store']);
        Route::put('projects/{project}', [ProjectController::class, 'update']);
        Route::patch('projects/{project}', [ProjectController::class, 'update']);
        Route::delete('projects/{project}', [ProjectController::class, 'destroy']);
        Route::post('customers', [CustomerController::class, 'store']);
        Route::put('customers/{customer}', [CustomerController::class, 'update']);
        Route::patch('customers/{customer}', [CustomerController::class, 'update']);
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy']);
        Route::post('projects/{project}/tasks', [TaskController::class, 'store']);
        Route::put('projects/{project}/tasks/{task}', [TaskController::class, 'update']);
        Route::patch('projects/{project}/tasks/{task}', [TaskController::class, 'update']);
        Route::delete('projects/{project}/tasks/{task}', [TaskController::class, 'destroy']);
        Route::put('devices/{device}', [DeviceController::class, 'update']);
        Route::patch('devices/{device}', [DeviceController::class, 'update']);
        Route::post('work-events/rebuild', [WorkEventController::class, 'rebuild']);
        Route::post('sync-conflicts/{syncConflict}/resolve', [SyncConflictController::class, 'resolve']);
    });
});
