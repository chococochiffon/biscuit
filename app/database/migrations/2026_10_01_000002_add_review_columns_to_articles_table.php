<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ユーザーの記事の承認のために、差し戻しの理由(review_comment)と、最初に承認して公開した日時(first_published_at)を追加する。
     * first_published_at が空の記事を承認したときだけ、公開開始日時を承認した日時にする(再承認では変えない)。
     */
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->text('review_comment')->nullable()->after('approval');
            $table->dateTime('first_published_at')->nullable()->after('review_comment');
        });

        // 公開済みの記事は公開開始日時を最初に公開した日時とみなす(再承認で公開開始日時を上書きしないため)
        DB::table('articles')->where('approval', 'published')->update(['first_published_at' => DB::raw('publication_start_datetime')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['review_comment', 'first_published_at']);
        });
    }
};
