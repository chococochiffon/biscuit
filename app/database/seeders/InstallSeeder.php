<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * インストーラーが入れる本番用の初期データ(App\Installer\ApplicationInstaller が呼ぶ)。
 * 開発用の DatabaseSeeder と違い、固定の管理者(admin@example.com)・サンプルのユーザー・記事・Q&A などは入れない
 * (管理者はインストーラーの管理者の段で作り、トップの見た目はデザインの段で作る)。何度呼んでもよい。
 */
class InstallSeeder extends Seeder
{
    public function run(): void
    {
        // 画像を設定していないときに使う既定の画像(サムネイル・サイトのアイコンなど)
        $this->call(DefaultImageSeeder::class);
        // 呼び出しコンテンツ・レイアウトの部品が使うデータ種別(記事・固定ページなど)
        $this->call(ContentModelRelationSeeder::class);
        // ページビルダーのテンプレート(ランディングページ・会社概要・お問い合わせ)
        $this->call(PageBuilderTemplateSeeder::class);
    }
}
