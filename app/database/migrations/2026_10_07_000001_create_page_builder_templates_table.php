<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ページビルダーのテンプレート(ページ全体の内容のひな形)のテーブルを作成する。
     * エディタでテンプレートを選ぶと、その内容を写して下書きにする(ページとはつながらず、あとでテンプレートを変えてもページは変わらない)。
     */
    public function up(): void
    {
        Schema::create('page_builder_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('テンプレートの名前');
            $table->string('description')->nullable()->comment('説明');
            $table->unsignedSmallInteger('schema_version')->default(1)->comment('ノードの木の JSON の構造の版');
            $table->json('content')->comment('内容(ノードの木)');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_builder_templates');
    }
};
