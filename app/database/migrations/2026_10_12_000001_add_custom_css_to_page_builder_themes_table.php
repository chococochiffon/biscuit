<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ページビルダーのテーマに、ビルダーで作ったすべてのページに効くサイト共通の Custom CSS を足す(スーパー管理者だけが書ける)。
     */
    public function up(): void
    {
        Schema::table('page_builder_themes', function (Blueprint $table) {
            $table->text('custom_css')->nullable()->after('body_font')->comment('サイト共通の Custom CSS(Support\Builder\CustomCss。ビルダーの部分にだけ効く)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('page_builder_themes', function (Blueprint $table) {
            $table->dropColumn('custom_css');
        });
    }
};
