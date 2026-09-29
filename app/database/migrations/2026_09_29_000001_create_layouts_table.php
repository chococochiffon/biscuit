<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 公開側のページの種類ごとのレイアウト(サイドバーの位置)のテーブルを作成する。
     */
    public function up(): void
    {
        Schema::create('layouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('page_type')->comment('ページの種類(LayoutPageType)');
            $table->unsignedTinyInteger('sidebar_position')->default(1)->comment('サイドバーの位置(SidebarPosition)');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('layouts');
    }
};
