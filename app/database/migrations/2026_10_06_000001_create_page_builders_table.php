<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ページビルダーで組み立てたページの内容(ノードの木の JSON)のテーブルを作成する。
     * 対象はトップ(page_type=top、single_page_id は null)と固定ページ(page_type=single_page)で、対象 1 件につき有効な行は 1 つ。
     * 編集中の内容(draft_content)と公開中の内容(published_content)を同じ行に持ち、公開側には公開中の内容だけを出す。
     */
    public function up(): void
    {
        // 文字列の連結は MySQL と SQLite で書き方が違う
        $target = DB::getDriverName() === 'sqlite'
            ? "page_type || ':' || COALESCE(single_page_id, 0)"
            : "CONCAT(page_type, ':', COALESCE(single_page_id, 0))";

        Schema::create('page_builders', function (Blueprint $table) use ($target) {
            $table->id();
            $table->string('page_type', 32)->comment('対象のページの種類(top・single_page)');
            $table->foreignId('single_page_id')->nullable()->constrained()->comment('対象の固定ページ(トップは null)');
            $table->unsignedSmallInteger('schema_version')->default(1)->comment('ノードの木の JSON の構造の版');
            $table->json('draft_content')->comment('編集中の内容(ノードの木)');
            $table->json('published_content')->nullable()->comment('公開中の内容(ノードの木。未公開なら null)');
            $table->dateTime('published_at')->nullable()->comment('最後に公開した日時(日本時間)');
            $table->softDeletes();
            $table->timestamps();

            // 論理削除していない行の間だけで、対象(ページの種類と固定ページ)の一意性を担保する
            $table->string('unique_target', 64)
                ->nullable()
                ->storedAs("CASE WHEN deleted_at IS NULL THEN {$target} ELSE NULL END")
                ->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_builders');
    }
};
