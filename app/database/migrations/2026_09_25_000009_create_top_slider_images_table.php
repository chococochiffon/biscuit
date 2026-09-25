<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 公開側トップのスライダーに表示する画像(16:9)とリンク先URL・並び順のテーブルを作成する。
     */
    public function up(): void
    {
        Schema::create('top_slider_images', function (Blueprint $table) {
            $table->id();
            $table->string('top_image');
            $table->string('url')->nullable()->default(null);
            $table->unsignedInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('top_slider_images');
    }
};
