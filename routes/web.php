<?php

use App\Auth\Controllers\AuthController;
use App\Inspection\Actions\GenerateExcel;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;


Route::middleware('guest')->group(function () {
    Volt::route('/login', 'auth::login')->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

Route::middleware('auth:worker')->group(function () {
    Route::post('/logout/worker', [AuthController::class, 'logout'])->name('worker.logout');
    Route::view('/', 'index')->name('landing-page');
});

Route::middleware('auth:web')->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::post('/logout/web', [AuthController::class, 'logout'])->name('web.logout');
    Route::get('/inspection/xbar/download', GenerateExcel::class)
        ->name('inspection.xbar.download');

    Volt::route('/reports', 'inspection::reports-browser')->name('reports.browser');

    Route::get('/symbols/{filename}', function (string $filename) {
        abort_unless(preg_match('/^[\w\-]+\.(png|jpe?g)$/i', $filename), 404);
        $path = storage_path('app/Symbol/' . $filename);
        abort_unless(file_exists($path), 404);
        return response()->file($path);
    })->middleware('auth')->name('symbols.show');
});
