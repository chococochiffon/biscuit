<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 二段階認証の確認コード(管理画面と chococo のマイページのログイン)。パスワードが正しいとメールで 6 桁のコードを送り、
     * コードを入力するとログインが完了する。どのログインへのコードかはチャレンジ(ランダムな文字列)で見分ける。
     * コード(bcrypt)とチャレンジ(SHA-256)はハッシュで持ち、使う・期限が切れる・送り直す・間違えすぎると使えなくなる(削除はしない)。
     */
    public function up(): void
    {
        Schema::create('login_codes', function (Blueprint $table) {
            $table->id();
            $table->morphs('authenticatable');
            $table->string('challenge', 64)->unique();
            $table->string('code');
            $table->dateTime('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('used_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_codes');
    }
};
