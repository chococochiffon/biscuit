<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * レイアウトのナビメニューの部品に並べる項目のテーブルを作成する。
     * リンク先は種類(link_type)に応じて、URL(url)・固定ページ(single_page_id)・カスタムページの種類(custom_page_type_id)のどれかを使う。
     */
    public function up(): void
    {
        Schema::create('layout_nav_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('layout_block_id')->constrained();
            $table->unsignedTinyInteger('link_type')->comment('リンク先の種類(NavItemLinkType)');
            $table->string('label')->nullable()->comment('表示名(固定ページ・カスタムページの一覧は空ならタイトル・表示名)');
            $table->string('url', 2048)->nullable();
            $table->foreignId('single_page_id')->nullable()->constrained();
            $table->foreignId('custom_page_type_id')->nullable()->constrained();
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
        Schema::dropIfExists('layout_nav_items');
    }
};
