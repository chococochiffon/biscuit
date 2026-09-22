<?php

namespace Database\Seeders;

use App\Models\SinglePage;
use App\Models\SinglePageDetail;
use Illuminate\Database\Seeder;

class SinglePageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $singlePage = SinglePage::query()->firstOrCreate(
            ['uri' => 'about'],
            [
                'title' => 'biscuitについて',
                'short_sentences' => 'biscuitは、シンプルで使いやすいコンテンツ管理システムです。',
                'taxonomy' => 'information',
                'top_page_view' => true,
                'link_list_view' => true,
                'sort_order' => 0,
            ]
        );

        SinglePageDetail::query()->firstOrCreate(
            ['single_page_id' => $singlePage->id, 'sub_title' => 'シンプルに、コンテンツを届ける。'],
            [
                'contents' => <<<'HTML'
                    <p>
                    biscuitは、Webサイトのコンテンツをシンプルに管理するためのCMSです。
                    固定ページやお知らせなど、Webサイトに必要なコンテンツをかんたんに作成・編集できます。
                    </p>

                    <h2>biscuitでできること</h2>

                    <ul>
                        <li>固定ページの作成・編集</li>
                        <li>コンテンツの分類</li>
                        <li>自由なURIの設定</li>
                        <li>HTMLを利用したコンテンツ作成</li>
                    </ul>

                    <h2>このページについて</h2>

                    <p>
                    このページは、biscuitのインストール時に作成されるサンプルページです。
                    管理画面から自由に編集・削除してください。
                    </p>
                    HTML,
                'sort_order' => 0,
            ]
        );
    }
}
