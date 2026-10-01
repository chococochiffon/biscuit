<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ページビルダーのグローバルコンポーネント(ヘッダー・CTA など、複数のページで使う共通のパーツ)のテーブルを作成する。
     * ページには「グローバルコンポーネント」のブロック(参照する id だけ)を置き、公開側にはこの公開中の内容を出す。
     * 内容はページと同じくセクションの並びで、編集中(draft_content)と公開中(published_content)を分けて持つ。
     */
    public function up(): void
    {
        Schema::create('page_builder_components', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('名前');
            $table->string('description')->nullable()->comment('説明');
            $table->unsignedSmallInteger('schema_version')->default(1)->comment('ノードの木の JSON の構造の版');
            $table->json('draft_content')->comment('編集中の内容(ノードの木)');
            $table->json('published_content')->nullable()->comment('公開中の内容(ノードの木。未公開なら null)');
            $table->dateTime('published_at')->nullable()->comment('最後に公開した日時(日本時間)');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_builder_components');
    }
};
