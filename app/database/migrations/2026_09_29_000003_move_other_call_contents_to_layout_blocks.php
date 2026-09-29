<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * サイト設定の呼び出しコンテンツのうち、設置場所「その他」(place = 3)のものをレイアウトの部品へ移す。
     * 公開側ではナビに固定ページ、フッターにそれ以外を並べていたため、固定ページ(single_pages)のものは
     * ナビメニューの部品が代わりに並べるとして移さず、それ以外はフッターの末尾に呼び出しコンテンツの部品として足す。
     * 移したかどうかにかかわらず、もとの呼び出しコンテンツは論理削除する。
     * (enum の値が後で変わってもこのマイグレーションの結果が変わらないよう、値は数値で書く)
     */
    public function up(): void
    {
        $now = now();
        $sortOrder = (int) DB::table('layout_blocks')->where('region', 3)->whereNull('deleted_at')->max('sort_order');

        $callContents = DB::table('call_contents')
            ->join('content_model_relations', 'content_model_relations.id', '=', 'call_contents.content_model_relation_id')
            ->where('call_contents.place', 3)
            ->whereNull('call_contents.deleted_at')
            ->orderBy('call_contents.sort_order')
            ->orderBy('call_contents.id')
            ->get(['call_contents.*', 'content_model_relations.table_name']);

        foreach ($callContents as $callContent) {
            if ($callContent->table_name !== 'single_pages') {
                DB::table('layout_blocks')->insert([
                    'region' => 3,
                    'block_type' => 6,
                    'title' => $callContent->title,
                    'subtitle' => $callContent->subtitle,
                    'call_type' => $callContent->call_type,
                    'content_model_relation_id' => $callContent->content_model_relation_id,
                    'view_count' => $callContent->view_count,
                    'sort_order' => ++$sortOrder,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::table('call_contents')->where('id', $callContent->id)->update(['deleted_at' => $now]);
        }
    }

    /**
     * 移し替えたデータは戻さない(論理削除した呼び出しコンテンツは deleted_at を消せば戻せる)。
     */
    public function down(): void
    {
        //
    }
};
