<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class WorkTrackerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // WorkTracker uses normal application services and intentionally has no hidden singleton state.
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/worktracker-api.php'));
        $this->loadRoutesFrom(base_path('routes/worktracker.php'));
    }
}
