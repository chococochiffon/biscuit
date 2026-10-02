<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ページビルダーのテーマ(ビルダーのブロックにだけ効く色とフォント)のテーブルを作成する。
     * テーマはサイトに 1 つだけで、未登録なら既定のテーマ(Support\Builder\ThemeRegistry)を使う。
     */
    public function up(): void
    {
        Schema::create('page_builder_themes', function (Blueprint $table) {
            $table->id();
            $table->json('colors')->comment('テーマの色(名前 → #rrggbb)');
            $table->string('heading_font', 50)->nullable()->comment('見出しのフォント(ThemeRegistry::FONTS のキー。null はサイトの既定)');
            $table->string('body_font', 50)->nullable()->comment('本文のフォント(ThemeRegistry::FONTS のキー。null はサイトの既定)');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_builder_themes');
    }
};
