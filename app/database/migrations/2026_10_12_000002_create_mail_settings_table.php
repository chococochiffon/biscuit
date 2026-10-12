<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * システムが使うメールの設定(管理画面の「メールの設定」で保存する)のテーブルを作成する。
     * 1 件だけ登録する前提で、登録があれば .env の MAIL_* より優先する(未登録なら .env の設定を使う)。
     * SMTP のパスワード・Resend の API キーは APP_KEY で暗号化して保存する。
     */
    public function up(): void
    {
        Schema::create('mail_settings', function (Blueprint $table) {
            $table->id();
            $table->string('driver', 20)->comment('送り方(smtp・resend)');
            $table->string('host')->nullable()->comment('SMTP のホスト');
            $table->unsignedInteger('port')->nullable()->comment('SMTP のポート');
            $table->string('encryption', 20)->nullable()->comment('SMTP の暗号化(starttls・ssl)');
            $table->string('username')->nullable()->comment('SMTP のユーザー名');
            $table->text('password')->nullable()->comment('SMTP のパスワード(暗号化)');
            $table->text('api_key')->nullable()->comment('Resend の API キー(暗号化)');
            $table->string('from_address')->comment('送信元のメールアドレス');
            $table->string('from_name')->nullable()->comment('送信元の名前(null は .env の MAIL_FROM_NAME)');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_settings');
    }
};
