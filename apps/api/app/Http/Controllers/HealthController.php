<?php

namespace App\Http\Controllers;

use Composer\InstalledVersions;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [];
        $ok = true;

        try {
            DB::select('select 1');
            $checks['database'] = [
                'status' => 'ok',
                'driver' => DB::connection()->getDriverName(),
            ];
        } catch (\Throwable) {
            $ok = false;
            $checks['database'] = [
                'status' => 'fail',
                'message' => 'database connection failed',
            ];
        }

        $requiredTables = [
            'users',
            'personal_access_tokens',
            'devices',
            'projects',
            'activity_sessions',
            'customers',
            'activity_types',
            'invoices',
            'worktracker_audit_logs',
        ];

        $missingTables = [];
        foreach ($requiredTables as $table) {
            try {
                if (! Schema::hasTable($table)) {
                    $missingTables[] = $table;
                }
            } catch (\Throwable) {
                $missingTables[] = $table;
            }
        }

        $checks['schema'] = [
            'status' => $missingTables === [] ? 'ok' : 'fail',
            'missing' => $missingTables,
        ];
        if ($missingTables !== []) {
            $ok = false;
        }

        $storageOk = is_dir(storage_path('framework')) && is_writable(storage_path());
        $checks['storage'] = ['status' => $storageOk ? 'ok' : 'fail'];
        if (! $storageOk) {
            $ok = false;
        }

        try {
            Cache::put('worktracker_health', time(), 10);
            $cacheOk = Cache::get('worktracker_health') !== null;
        } catch (\Throwable) {
            $cacheOk = false;
        }

        $checks['cache'] = ['status' => $cacheOk ? 'ok' : 'fail'];
        if (! $cacheOk) {
            $ok = false;
        }

        $requiredRoutes = [
            'login',
            'worktracker.dashboard',
            'worktracker.activities.index',
            'worktracker.reports.index',
        ];
        $missingRoutes = array_values(array_filter(
            $requiredRoutes,
            static fn (string $name): bool => ! Route::has($name)
        ));

        $checks['routes'] = [
            'status' => $missingRoutes === [] ? 'ok' : 'fail',
            'missing' => $missingRoutes,
        ];
        if ($missingRoutes !== []) {
            $ok = false;
        }

        $sanctumInstalled = class_exists(Sanctum::class);
        $sanctumVersion = null;

        if ($sanctumInstalled && class_exists(InstalledVersions::class)) {
            try {
                $sanctumVersion = InstalledVersions::getPrettyVersion('laravel/sanctum');
            } catch (\Throwable) {
                $sanctumVersion = null;
            }
        }

        $checks['sanctum'] = [
            'status' => $sanctumInstalled ? 'ok' : 'fail',
            'version' => $sanctumVersion,
        ];
        if (! $sanctumInstalled) {
            $ok = false;
        }

        return response()->json([
            'status' => $ok ? 'ok' : 'fail',
            'app' => 'WorkTracker',
            'version' => '0.1.0-alpha.7.2',
            'environment' => app()->environment(),
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'checks' => $checks,
            'time' => now()->toIso8601String(),
        ], $ok ? 200 : 503);
    }
}
