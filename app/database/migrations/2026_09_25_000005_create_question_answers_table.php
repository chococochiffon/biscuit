<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Q&A の親テーブルを作成する。簡易版(質問・回答の短文)を入力した場合はトップ表示用(top_view)になる。
     */
    public function up(): void
    {
        Schema::create('question_answers', function (Blueprint $table) {
            $table->id();
            $table->text('short_question_text')->nullable()->default(null);
            $table->text('short_answer_text')->nullable()->default(null);
            $table->boolean('top_view')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_answers');
    }
};
