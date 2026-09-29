<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 公開側のレイアウトの領域(ヘッダー・サイドバー・フッター)に置く部品のテーブルを作成する。
     * 呼び出し方・データ種別・表示件数は呼び出しコンテンツの部品、本文は自由テキストの部品だけが使う。
     */
    public function up(): void
    {
        Schema::create('layout_blocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('region')->comment('領域(LayoutRegion)');
            $table->unsignedTinyInteger('block_type')->comment('部品の種類(LayoutBlockType)');
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->unsignedTinyInteger('call_type')->nullable()->comment('呼び出し方(CallType)');
            $table->foreignId('content_model_relation_id')->nullable()->constrained();
            $table->unsignedInteger('view_count')->nullable();
            $table->text('content')->nullable();
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
        Schema::dropIfExists('layout_blocks');
    }
};
