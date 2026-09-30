<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ユーザーが有効か(active_flag)を追加する。管理者に招待されたユーザーは、招待のリンクからプロフィールとパスワードを
     * 登録するまで無効(0)で、ログインできない。既存のユーザーはすでに使っているため有効にする。
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('active_flag')->default(false)->after('skip_approval');
        });

        DB::table('users')->update(['active_flag' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('active_flag');
        });
    }
};
