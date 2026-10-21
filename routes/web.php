<?php

use App\Auth\Controllers\AuthController;
use App\Http\Controllers\SymbolController;
use App\Inspection\Actions\GenerateExcel;
use App\Inspection\Models\XBarCycleLog;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;


Route::middleware('guest')->group(function () {
    Volt::route('/login', 'auth::login')->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

Route::middleware('auth:worker,web')->group(function () {
    Route::view('/', 'index')->name('landing-page');
});

Route::middleware('auth:worker')->group(function () {
    Route::post('/logout/worker', [AuthController::class, 'logout'])->name('worker.logout');
});

Route::middleware(['auth:web', 'staff-only'])->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::get('/inspection/xbar/download', GenerateExcel::class)
        ->name('inspection.xbar.download');
    Volt::route('/reports', 'inspection::reports-browser')->name('reports.browser');
    Route::get('/symbols/{symbol}', SymbolController::class)
        ->where('symbol', '[0-9]+')
        ->name('symbols.show');
});

Route::middleware('auth:web')->post('/logout/web', [AuthController::class, 'logout'])
    ->name('web.logout');

Route::middleware(['auth:web', 'can:confirm-lots'])
    ->get('/lot-confirmation', fn() => view('lot-confirmation'))
    ->name('lot-confirmation');

Route::get('/xbar/download/{transaction}', function (string $transaction) {
    $path = XBarCycleLog::where('TransactionId', $transaction)->value('FilePath');

    $directory = realpath(storage_path('app/excel-xbar'));
    $file      = $path ? realpath($path) : false;

    abort_unless(
        $directory && $file && str_starts_with($file, $directory . DIRECTORY_SEPARATOR) && is_file($file),
        404
    );

    return response()->download($file);
})->middleware('signed')->name('xbar.download');
