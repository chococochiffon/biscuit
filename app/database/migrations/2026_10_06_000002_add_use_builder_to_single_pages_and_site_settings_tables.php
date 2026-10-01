<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ページビルダーの内容を公開側に出すかの切り替えを追加する。
     * 固定ページの use_builder は、true なら詳細の行の代わりにビルダーの公開中の内容を出す。
     * サイト設定の top_use_builder は、true ならトップのスライダーの下にビルダーの公開中の内容を出す。
     */
    public function up(): void
    {
        Schema::table('single_pages', function (Blueprint $table) {
            $table->boolean('use_builder')->default(false)->after('link_list_view');
        });

        Schema::table('site_settings', function (Blueprint $table) {
            $table->boolean('top_use_builder')->default(false)->after('api_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('single_pages', function (Blueprint $table) {
            $table->dropColumn('use_builder');
        });

        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn('top_use_builder');
        });
    }
};
