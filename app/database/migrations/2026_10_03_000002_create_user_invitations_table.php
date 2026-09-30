<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 管理者からユーザーへの招待(メールのリンク)。トークンはハッシュ(SHA-256)で持ち、有効期限を過ぎる・受諾する・再送で
     * 新しい招待を出すと使えなくなる。再送のときも古い招待は削除せず、有効期限を切らして無効にする。
     */
    public function up(): void
    {
        Schema::create('user_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('administrator_id')->nullable()->constrained();
            $table->string('token', 64)->unique();
            $table->dateTime('expires_at');
            $table->dateTime('accepted_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_invitations');
    }
};
