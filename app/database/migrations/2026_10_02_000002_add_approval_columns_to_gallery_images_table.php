<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * マイページからユーザーがギャラリーに画像を投稿できるよう、投稿したユーザー(user_id。null なら管理者の投稿)と
     * 公開ステータス(approval。記事と同じ draft・pending・published)、差し戻しの理由(review_comment)を追加する。
     * これまでの画像はすべて管理者の投稿で公開中のため、approval の既定値は published にする。
     */
    public function up(): void
    {
        Schema::table('gallery_images', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->default(null)->after('id')->constrained();
            $table->string('approval')->default('published')->after('sort_order');
            $table->text('review_comment')->nullable()->after('approval');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gallery_images', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['approval', 'review_comment']);
        });
    }
};
