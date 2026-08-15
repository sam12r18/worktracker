<?php
use App\Http\Controllers\Auth\LoginController; use App\Http\Controllers\HealthController; use Illuminate\Support\Facades\Route;
Route::get('/',fn()=>auth()->check()?redirect()->route('worktracker.dashboard'):redirect()->route('login'));
Route::middleware('guest')->group(function(){Route::get('/login',[LoginController::class,'create'])->name('login');Route::post('/login',[LoginController::class,'store'])->name('login.store');});
Route::post('/logout',[LoginController::class,'destroy'])->middleware('auth')->name('logout');
Route::get('/worktracker/health',HealthController::class)->name('worktracker.health');
