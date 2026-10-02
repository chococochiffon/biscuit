<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ページビルダーのコンポーネントに種類(global: グローバルコンポーネント、custom: 使うたびに一部を差し替えられる独自コンポーネント)を足す。
     * 今あるコンポーネントはグローバルコンポーネントにする。
     */
    public function up(): void
    {
        Schema::table('page_builder_components', function (Blueprint $table) {
            $table->string('kind', 20)->default('global')->after('id')->comment('種類(global・custom。BuilderComponentKind)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('page_builder_components', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
