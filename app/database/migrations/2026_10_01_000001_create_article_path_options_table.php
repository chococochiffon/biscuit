<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ユーザー(chococo のマイページ)が記事を投稿するときに選ぶ投稿先(表示名・親パス・並び順)のテーブルを作成する。
     */
    public function up(): void
    {
        Schema::create('article_path_options', function (Blueprint $table) {
            $table->id();
            $table->string('label', 128);
            $table->string('parent_path');
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
        Schema::dropIfExists('article_path_options');
    }
};
