<?php

use App\Http\Controllers\WorkTracker\BillingController;
use App\Http\Controllers\WorkTracker\InvoiceController;
use App\Http\Controllers\WorkTracker\WorkReportController;
use App\Http\Controllers\WorkTracker\ActivityHistoryController;
use App\Http\Controllers\WorkTracker\CustomerManagementController;
use App\Http\Controllers\WorkTracker\ProjectManagementController;
use App\Http\Controllers\WorkTracker\ProjectRuleManagementController;
use App\Http\Controllers\WorkTracker\TaskManagementController;

use App\Http\Controllers\Web\WorkTrackerAccessTokenController;
use App\Http\Controllers\Web\WorkTrackerDashboardController;
use App\Http\Middleware\EnsureWorkTrackerDashboardAccess;
use App\Http\Middleware\RequireWorkTrackerHttps;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', RequireWorkTrackerHttps::class, EnsureWorkTrackerDashboardAccess::class])
    ->prefix('worktracker')->name('worktracker.')->group(function () {
        Route::get('/', [WorkTrackerDashboardController::class, 'dashboard'])->name('dashboard');
        Route::get('/conflicts', [WorkTrackerDashboardController::class, 'conflicts'])->name('conflicts');
        Route::post('/conflicts/{syncConflict}/resolve', [WorkTrackerDashboardController::class, 'resolveConflict'])->name('conflicts.resolve');
        Route::post('/devices/{device}', [WorkTrackerDashboardController::class, 'updateDevice'])->name('devices.update');
        Route::post('/devices/{device}/revoke', [WorkTrackerDashboardController::class, 'revokeDevice'])->name('devices.revoke');
        Route::post('/devices/{device}/restore', [WorkTrackerDashboardController::class, 'restoreDevice'])->name('devices.restore');

        Route::get('/access-tokens', [WorkTrackerAccessTokenController::class, 'index'])->name('tokens.index');
        Route::post('/access-tokens', [WorkTrackerAccessTokenController::class, 'store'])->name('tokens.store');
        Route::delete('/access-tokens/{tokenId}', [WorkTrackerAccessTokenController::class, 'destroy'])->name('tokens.destroy');

        Route::get('/projects', [ProjectManagementController::class, 'index'])->name('projects.index');
        Route::post('/projects', [ProjectManagementController::class, 'store'])->name('projects.store');
        Route::get('/projects/{project}', [ProjectManagementController::class, 'show'])->name('projects.show');
        Route::post('/projects/{project}', [ProjectManagementController::class, 'update'])->name('projects.update');
        Route::post('/projects/{project}/archive', [ProjectManagementController::class, 'archive'])->name('projects.archive');
        Route::post('/projects/{project}/restore', [ProjectManagementController::class, 'restore'])->name('projects.restore');
        Route::post('/projects/{project}/rules', [ProjectRuleManagementController::class, 'store'])->name('projects.rules.store');
        Route::post('/projects/{project}/rules/{rule}', [ProjectRuleManagementController::class, 'update'])->name('projects.rules.update');
        Route::delete('/projects/{project}/rules/{rule}', [ProjectRuleManagementController::class, 'destroy'])->name('projects.rules.destroy');
        Route::post('/projects/{project}/tasks', [TaskManagementController::class, 'store'])->name('projects.tasks.store');
        Route::post('/projects/{project}/tasks/{task}', [TaskManagementController::class, 'update'])->name('projects.tasks.update');
        Route::delete('/projects/{project}/tasks/{task}', [TaskManagementController::class, 'destroy'])->name('projects.tasks.destroy');

        Route::get('/customers', [CustomerManagementController::class, 'index'])->name('customers.index');
        Route::post('/customers', [CustomerManagementController::class, 'store'])->name('customers.store');
        Route::get('/customers/{customer}', [CustomerManagementController::class, 'show'])->name('customers.show');
        Route::post('/customers/{customer}', [CustomerManagementController::class, 'update'])->name('customers.update');
        Route::post('/customers/{customer}/toggle', [CustomerManagementController::class, 'toggle'])->name('customers.toggle');

        Route::get('/billing', [BillingController::class, 'index'])->name('billing');
        Route::post('/billing/preview', [BillingController::class, 'preview'])->name('billing.preview');
        Route::post('/billing/customers', [BillingController::class, 'storeCustomer'])->name('billing.customers.store');
        Route::post('/billing/customers/{customerId}', [BillingController::class, 'updateCustomer'])->name('billing.customers.update');
        Route::post('/billing/activity-types', [BillingController::class, 'storeActivityType'])->name('billing.activity-types.store');
        Route::post('/billing/activity-types/{activityTypeId}', [BillingController::class, 'updateActivityType'])->name('billing.activity-types.update');
        Route::post('/billing/projects/{projectId}', [BillingController::class, 'updateProjectPricing'])->name('billing.projects.update');
        Route::post('/billing/overrides', [BillingController::class, 'storeOverride'])->name('billing.overrides.store');
        Route::post('/billing/overrides/{override}', [BillingController::class, 'updateOverride'])->name('billing.overrides.update');
        Route::post('/billing/overrides/{override}/expire', [BillingController::class, 'expireOverride'])->name('billing.overrides.expire');

        Route::get('/activities', [ActivityHistoryController::class, 'index'])->name('activities.index');
        Route::post('/activities/{activity}', [ActivityHistoryController::class, 'update'])->name('activities.update');
        Route::get('/audit', [ActivityHistoryController::class, 'audit'])->name('audit.index');
        Route::get('/reports', [WorkReportController::class, 'index'])->name('reports.index');

        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::post('/invoices/generate', [InvoiceController::class, 'generate'])->name('invoices.generate');
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::post('/invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
        Route::post('/invoices/{invoice}/finalize', [InvoiceController::class, 'finalize'])->name('invoices.finalize');
        Route::get('/invoices/{invoice}/excel', [InvoiceController::class, 'excel'])->name('invoices.excel');
        Route::get('/invoices/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
    });

