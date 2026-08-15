<?php
namespace App\Providers;
use Illuminate\Support\Facades\Route; use Illuminate\Support\ServiceProvider;
class WorkTrackerServiceProvider extends ServiceProvider {
 public function register():void{}
 public function boot():void{
  Route::middleware('api')->prefix('api')->group(base_path('routes/worktracker-api.php'));
  $this->loadRoutesFrom(base_path('routes/worktracker.php'));
 }
}
