<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 管理者・ユーザーに、二段階認証の方法(メールの確認コードか Google Authenticator などの認証アプリ(TOTP))の列を足す。
     * 認証アプリの秘密鍵は APP_KEY で暗号化して保存し、同じコードを 2 回使わせないよう最後に使ったコードの時刻(30 秒ごとの番号)を持つ。
     */
    public function up(): void
    {
        foreach (['administrators', 'users'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('two_factor_method', 20)->default('email')->comment('二段階認証の方法(email・totp)');
                $table->text('two_factor_secret')->nullable()->comment('認証アプリ(TOTP)の秘密鍵(暗号化)');
                $table->timestamp('two_factor_confirmed_at')->nullable()->comment('認証アプリを設定した日時');
                $table->unsignedBigInteger('two_factor_last_used_step')->nullable()->comment('最後に使った認証アプリのコードの時刻(30 秒ごとの番号)');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['administrators', 'users'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['two_factor_method', 'two_factor_secret', 'two_factor_confirmed_at', 'two_factor_last_used_step']);
            });
        }
    }
};
