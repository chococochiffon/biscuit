<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 監査ログ(誰が・いつ・何に・何をしたか)のテーブルを作成する。
     * 追記するだけで変更・削除しないため、論理削除の方針の例外として deleted_at・updated_at を持たない。
     * 操作者・対象はあとで削除・改名されても読めるよう、名前を記録時点の値で残す(外部キーは張らない)。
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type', 32)->nullable()->comment('操作者の種類(administrator など。ログイン失敗は null)');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name')->nullable()->comment('記録時点の操作者の名前');
            $table->string('action', 32)->comment('操作(AuditAction)');
            $table->string('subject_type', 128)->nullable()->comment('対象の種類(例: article、custom_page:recipe)');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable()->comment('記録時点の対象の名前');
            $table->json('changes')->nullable()->comment('変更内容(項目名 → [変更前, 変更後])');
            $table->json('metadata')->nullable()->comment('補足(入れ子の行の増減・並び順・一括操作の件数など)');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('route_name')->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['actor_type', 'actor_id']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
