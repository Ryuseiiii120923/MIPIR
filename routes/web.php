<?php

use App\Auth\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;


Route::middleware('guest')->group(function(){
Volt::route('/login', 'auth::login')->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

Route::middleware('auth:worker')->group(function(){

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::view('/', 'index')->name('landing-page');

});

Route::middleware('auth:web')->group(function(){
    Route::view('/dashboard', 'dashboard')->name('dashboard');
     Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});