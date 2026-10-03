<?php

use App\Http\Controllers\Installer\DatabaseController;
use App\Http\Controllers\Installer\PendingStepController;
use App\Http\Controllers\Installer\WelcomeController;
use App\Installer\InstallerStep;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| インストーラー(/install)
|--------------------------------------------------------------------------
|
| ミドルウェアのグループ installer(bootstrap/app.php)で動かす。DB ができる前から動かすため、セッション・キャッシュはファイル。
| インストール済みなら入れない(EnsureNotInstalled)。段は前の段を終えないと入れない(installer.step)。
| ブラウザから受け取るのは入力値だけで、命令の文字列は受け取らない(実行する処理はサーバー側で決めている)。
|
*/

Route::get('install', [WelcomeController::class, 'show'])->name('installer.requirements');
Route::post('install', [WelcomeController::class, 'start'])->name('installer.start');

Route::middleware('installer.step:database')->group(function () {
    Route::get('install/database', [DatabaseController::class, 'show'])->name('installer.database');
    Route::post('install/database', [DatabaseController::class, 'store'])->name('installer.database.store');
    Route::post('install/database/generate', [DatabaseController::class, 'generate'])->name('installer.database.generate');
});

// まだ作っていない段(段ごとのコントローラーに置き換えていく)
foreach (array_slice(InstallerStep::cases(), 2) as $step) {
    Route::get("install/{$step->value}", PendingStepController::class)
        ->defaults('step', $step->value)
        ->middleware("installer.step:{$step->value}")
        ->name($step->routeName());
}
