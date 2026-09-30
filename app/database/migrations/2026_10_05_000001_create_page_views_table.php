<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PV(公開側のページが表示された記録)の生データのテーブルを作成する。記録は Services\PageViewService を使う。
     * 閲覧の記録を追記するだけで変更しないため、論理削除の方針の例外として deleted_at を持たない。
     * 記録は公開側の表示ごとに増えるため、書き込みを軽くするよう外部キーは張らず、インデックスも集計に使うものだけにする。
     */
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->string('content_type', 128)->comment('コンテンツの種類(top・article・single_page・custom_page:種類の名前・custom_page_list:種類の名前)');
            $table->unsignedBigInteger('content_id')->nullable()->comment('コンテンツの id(トップ・カスタムページの一覧は null)');
            $table->string('path')->comment('閲覧した公開側 URL のパス(ドメインを含まない)');
            $table->unsignedBigInteger('user_id')->nullable()->comment('マイページにログイン中のユーザー');
            $table->uuid('visitor_id')->comment('訪問者の識別子(chococo の Cookie に入れたランダムな UUID)');
            $table->char('session_id', 64)->nullable()->comment('chococo のセッションの識別子の HMAC-SHA256');
            $table->char('ip_hash', 64)->nullable()->comment('IP アドレスの HMAC-SHA256(IP アドレスそのものは保存しない)');
            $table->string('user_agent', 512)->nullable();
            $table->string('referer', 2048)->nullable();
            $table->dateTime('viewed_at')->comment('閲覧した日時(日本時間)');
            $table->timestamps();

            // 期間ごとの PV・UU、日別の推移
            $table->index('viewed_at');
            // コンテンツごとの PV・人気コンテンツのランキング(content_type だけ、content_type と content_id だけの絞り込みにも使える)
            $table->index(['content_type', 'content_id', 'viewed_at']);
            // 訪問者ごとの集計・一定時間内の重複アクセスの判定
            $table->index(['visitor_id', 'viewed_at']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
