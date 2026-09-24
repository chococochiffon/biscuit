<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 呼び出しコンテンツの並び順(sort_order、同じ設置場所の中で昇順に表示する)を追加する。
     */
    public function up(): void
    {
        Schema::table('call_contents', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('place');
        });

        // 既存の行は登録順(id順)を並び順にする
        foreach (DB::table('call_contents')->orderBy('id')->pluck('id') as $index => $id) {
            DB::table('call_contents')->where('id', $id)->update(['sort_order' => $index]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('call_contents', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
