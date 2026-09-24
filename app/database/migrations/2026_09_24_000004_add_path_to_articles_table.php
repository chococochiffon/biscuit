<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 親パス(parent_path)・スラッグ(slug、未入力なら記事番号を使う)と公開側URLのパス(path)を追加する。
     * path は論理削除されていない行の間でのみ一意にする(unique_path)。
     */
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('parent_path')->nullable()->after('thumbnail');
            $table->string('slug')->nullable()->after('parent_path');
            $table->string('path')->nullable()->after('slug');
        });

        // 既存の記事は親パス・スラッグなしとして /{記事番号} にする
        foreach (DB::table('articles')->pluck('id') as $id) {
            DB::table('articles')->where('id', $id)->update(['path' => '/'.$id]);
        }

        Schema::table('articles', function (Blueprint $table) {
            $table->string('unique_path')
                ->nullable()
                ->storedAs('CASE WHEN deleted_at IS NULL THEN path ELSE NULL END')
                ->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropUnique(['unique_path']);
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['unique_path', 'parent_path', 'slug', 'path']);
        });
    }
};
