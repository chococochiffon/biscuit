<?php

use App\Http\Controllers\AdministratorController;
use App\Http\Controllers\Auth\AdministratorSessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [AdministratorSessionController::class, 'create'])->name('login');
        Route::post('login', [AdministratorSessionController::class, 'store'])
            ->middleware('throttle:admin-login')
            ->name('login.store');
    });

    Route::post('logout', [AdministratorSessionController::class, 'destroy'])
        ->middleware('auth:admin')
        ->name('logout');
});

Route::resource('admin', AdministratorController::class)
    ->parameters(['admin' => 'administrator'])
    ->middleware('auth:admin');
