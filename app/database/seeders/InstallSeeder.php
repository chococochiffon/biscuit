<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * インストーラーが入れる本番用の初期データ(App\Installer\ApplicationInstaller が呼ぶ)。
 * 開発用の DatabaseSeeder と違い、管理者・ユーザーのシーダーは使わない(管理者はインストーラーの管理者の段で作る)。
 * デザインの段でトップにビルダーを使うかどうかにかかわらず、公開側の標準のトップ(呼び出しコンテンツ)に並べる
 * スライダー画像・記事・固定ページ・Q&A・ギャラリーのサンプルと、それを呼び出すデータ種別・呼び出しコンテンツを入れる。何度呼んでもよい。
 */
class InstallSeeder extends Seeder
{
    public function run(): void
    {
        // 画像を設定していないときに使う既定の画像(サムネイル・サイトのアイコンなど)
        $this->call(DefaultImageSeeder::class);
        // 呼び出しコンテンツ・レイアウトの部品が使うデータ種別(記事・固定ページなど)と、トップ・本文ページの呼び出しコンテンツ
        $this->call(ContentModelRelationSeeder::class);
        $this->call(CallContentSeeder::class);
        // ページビルダーのテンプレート(ランディングページ・会社概要・お問い合わせ)
        $this->call(PageBuilderTemplateSeeder::class);
        // トップのスライダー画像(3 枚。登録済みなら登録しない)
        $this->call(TopSliderImageSeeder::class);
        // 記事のサンプル(タグつき)と、ユーザーが記事を投稿するときに選ぶ投稿先
        $this->call(ArticleSeeder::class);
        $this->call(ArticlePathOptionSeeder::class);
        // 固定ページのサンプル(biscuit について)
        $this->call(SinglePageSeeder::class);
        // Q&A のサンプル(簡易版と分岐あり)
        $this->call(QuestionAnswerSeeder::class);
        // ギャラリーの最初の画像(分類つきの 4 枚。登録済みなら登録しない)
        $this->call(GallerySeeder::class);
    }
}
