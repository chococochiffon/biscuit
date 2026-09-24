<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 呼び出しコンテンツに公開側で表示する見出し(title)・小見出し(subtitle)を追加する。
     * 呼び出し名(call_name)は管理用のラベルのままとし、見出しが空の枠は見出しなしで表示する。
     */
    public function up(): void
    {
        Schema::table('call_contents', function (Blueprint $table) {
            $table->string('title')->nullable()->after('call_name');
            $table->string('subtitle')->nullable()->after('title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('call_contents', function (Blueprint $table) {
            $table->dropColumn(['title', 'subtitle']);
        });
    }
};
