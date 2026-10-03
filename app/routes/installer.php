<?php

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

foreach (array_slice(InstallerStep::cases(), 1) as $step) {
    Route::get("install/{$step->value}", PendingStepController::class)
        ->defaults('step', $step->value)
        ->middleware("installer.step:{$step->value}")
        ->name($step->routeName());
}
