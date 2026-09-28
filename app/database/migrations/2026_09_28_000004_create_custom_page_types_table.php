<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * カスタムページの種類(カスタム名・表示名・ベースの型・並び順)のテーブルを作成する。
     * 種類ごとのテーブル(user_make_○○ など)は、種類の登録時に CustomPageSchema が作る。
     * カスタム名は論理削除後も再利用しない(作ったテーブルを残すため)ので、削除済みを含めて一意にする。
     */
    public function up(): void
    {
        Schema::create('custom_page_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30)->unique();
            $table->string('label', 128);
            $table->unsignedTinyInteger('base_type');
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
        Schema::dropIfExists('custom_page_types');
    }
};
