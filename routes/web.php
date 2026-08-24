<?php

use App\Auth\Controllers\AuthController;
use App\Inspection\Actions\GenerateExcel;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Inspection\Services\Excel\InspectionXBarService;


Route::middleware('guest')->group(function(){
Volt::route('/login', 'auth::login')->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

Route::middleware('auth:worker')->group(function(){
    Route::post('/logout/worker', [AuthController::class, 'logout'])->name('worker.logout');
    Route::view('/', 'index')->name('landing-page');
});

Route::middleware('auth:web')->group(function(){
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::post('/logout/web', [AuthController::class, 'logout'])->name('web.logout');
    Route::get('/inspection/xbar/{ppf}/download', GenerateExcel::class)
    ->name('inspection.xbar.download');
});
