<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ページの種類ごとに、公開側でパンくずを表示するかどうかの列を追加する(トップ以外は表示する)。
     */
    public function up(): void
    {
        Schema::table('layouts', function (Blueprint $table) {
            $table->boolean('show_breadcrumbs')->default(true)->after('sidebar_position');
        });

        // トップ(page_type = 1)はパンくずを表示しない
        DB::table('layouts')->where('page_type', 1)->update(['show_breadcrumbs' => false]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('layouts', function (Blueprint $table) {
            $table->dropColumn('show_breadcrumbs');
        });
    }
};
