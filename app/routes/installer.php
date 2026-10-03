<?php

use App\Http\Controllers\Installer\AdministratorController;
use App\Http\Controllers\Installer\ApplicationController;
use App\Http\Controllers\Installer\DatabaseController;
use App\Http\Controllers\Installer\DesignController;
use App\Http\Controllers\Installer\FinalizeController;
use App\Http\Controllers\Installer\MailController;
use App\Http\Controllers\Installer\SiteController;
use App\Http\Controllers\Installer\WelcomeController;
use App\Http\Controllers\PageBuilderJsonController;
use App\Http\Controllers\PageBuilderTemplateJsonController;
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

Route::middleware('installer.step:application')->group(function () {
    Route::get('install/application', [ApplicationController::class, 'show'])->name('installer.application');
    Route::post('install/application', [ApplicationController::class, 'start'])->name('installer.application.start');
});

// 段ごとに、表示(GET)と保存(POST)を同じ形で並べる
foreach ([
    'site' => SiteController::class,
    'mail' => MailController::class,
    'administrator' => AdministratorController::class,
    'design' => DesignController::class,
    'finalize' => FinalizeController::class,
] as $step => $controller) {
    Route::middleware("installer.step:{$step}")->group(function () use ($step, $controller) {
        Route::get("install/{$step}", [$controller, 'show'])->name("installer.{$step}");
        Route::post("install/{$step}", [$controller, 'store'])->name("installer.{$step}.store");
    });
}

Route::post('install/administrator/generate', [AdministratorController::class, 'generate'])
    ->middleware('installer.step:administrator')
    ->name('installer.administrator.generate');

// デザインの段のビルダー。最初の管理者を作ったセッションだけが使え(installer.builder)、JSON は管理画面のビルダーと同じ処理(トップ)を使う。
// インストールを終えると、ほかのインストーラーのルートと同じく入れなくなる
Route::post('install/design/unlock', [DesignController::class, 'unlock'])
    ->middleware('installer.step:design')
    ->name('installer.design.unlock');

Route::middleware(['installer.step:design', 'installer.builder'])->group(function () {
    Route::get('install/design/builder', [DesignController::class, 'builder'])->name('installer.design.builder');
    Route::get('install/design/builder/json', [PageBuilderJsonController::class, 'show'])->name('installer.design.builder.json.show');
    Route::put('install/design/builder/json', [PageBuilderJsonController::class, 'update'])->name('installer.design.builder.json.update');
    Route::get('install/design/builder/json/preview-url', [PageBuilderJsonController::class, 'previewUrl'])->name('installer.design.builder.json.preview-url');
    Route::post('install/design/builder/json/images', [PageBuilderJsonController::class, 'storeImage'])->name('installer.design.builder.json.images');
    Route::get('install/design/builder/json/templates', [PageBuilderTemplateJsonController::class, 'index'])->name('installer.design.builder.json.templates');
    Route::get('install/design/builder/json/article-list', [PageBuilderJsonController::class, 'articleList'])->name('installer.design.builder.json.article-list');
    Route::get('install/design/builder/json/navigation', [PageBuilderJsonController::class, 'navigation'])->name('installer.design.builder.json.navigation');
    Route::get('install/design/builder/json/gallery', [PageBuilderJsonController::class, 'gallery'])->name('installer.design.builder.json.gallery');
});
