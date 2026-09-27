<?php

namespace Database\Seeders;

use App\Enums\ArticleApprovalStatus;
use App\Models\Article;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class ArticleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->articles() as $data) {
            $article = Article::query()->firstOrNew([
                'parent_path' => $data['parent_path'],
                'slug' => $data['slug'],
            ]);

            if (! $article->exists) {
                // DatabaseSeeder はモデルイベントを止めて実行するため、HasPath の代わりに path をここで組み立てる
                $article->forceFill([
                    'title' => $data['title'],
                    'content' => $data['content'],
                    'approval' => ArticleApprovalStatus::Published,
                    'publication_start_datetime' => now(),
                    'path' => Article::buildPath($data['parent_path'], $data['slug']),
                ])->save();
            }

            // 記事の保存時と同じく、未登録のタグは作成してから紐づける
            $tagIds = collect($data['tags'])
                ->map(fn (string $tagName) => Tag::query()->firstOrCreate(['tag_name' => $tagName])->id);

            $article->tags()->syncWithoutDetaching($tagIds);
        }
    }

    /**
     * 初期データの記事(公開日はデータ投入日)。
     *
     * @return list<array{title: string, content: string, parent_path: string, slug: string, tags: list<string>}>
     */
    private function articles(): array
    {
        return [
            [
                'title' => 'biscuitへようこそ',
                'content' => <<<'HTML'
                    <p>biscuitは、シンプルで使いやすいコンテンツ管理システムです。</p>

                    <p>
                        記事の作成や編集、カテゴリー・タグによるコンテンツの整理など、
                        Webサイトを運営するための基本的な機能を備えています。
                    </p>

                    <h2>biscuitでできること</h2>

                    <ul>
                        <li>記事の作成・編集</li>
                        <li>カテゴリーやタグによるコンテンツ管理</li>
                        <li>画像などのメディア管理</li>
                        <li>公開・非公開などのステータス管理</li>
                    </ul>

                    <p>
                        まずはこの記事を編集したり、新しい記事を作成したりして、
                        biscuitの使い方を試してみてください。
                    </p>

                    <p>あなたのコンテンツづくりを、biscuitがお手伝いします。</p>
                    HTML,
                'parent_path' => 'blog',
                'slug' => 'welcome-to-biscuit',
                'tags' => ['biscuit', 'CMS', 'はじめに'],
            ],
            [
                'title' => '春に訪れたいおすすめカフェ3選',
                'content' => <<<'HTML'
                    <p>
                        暖かい日が増えて、外へ出かけるのが楽しい季節になりました。
                    </p>

                    <p>
                        今回は、ゆっくりとした時間を過ごしたい日におすすめのカフェを3つ紹介します。
                    </p>

                    <h2>景色を楽しめるカフェ</h2>

                    <p>
                        大きな窓から街や自然を眺めながら、ゆっくりコーヒーを楽しめるカフェ。
                        天気のいい日は、窓際の席で過ごすのがおすすめです。
                    </p>

                    <h2>コーヒーにこだわったカフェ</h2>

                    <p>
                        豆の種類や焙煎方法にこだわった一杯を楽しめるお店です。
                        気になる豆があれば、お店の人におすすめを聞いてみるのもいいでしょう。
                    </p>

                    <h2>焼き菓子がおいしいカフェ</h2>

                    <p>
                        クッキーやスコーンなど、焼きたてのお菓子を楽しめるカフェです。
                        コーヒーや紅茶と一緒に楽しめば、ちょっと特別なおやつ時間になります。
                    </p>

                    <p>
                        お気に入りの本を一冊持って、のんびりとカフェ巡りを楽しんでみてはいかがでしょうか。
                    </p>
                    HTML,
                'parent_path' => 'blog/life',
                'slug' => 'spring-cafe',
                'tags' => ['カフェ', '暮らし', '春', 'おすすめ'],
            ],
            [
                'title' => 'biscuit v1.0をリリースしました',
                'content' => <<<'HTML'
                    <p>
                        いつもbiscuitをご利用いただきありがとうございます。
                    </p>

                    <p>
                        biscuit v1.0をリリースしました。
                        今回のアップデートでは、記事編集画面を中心にいくつかの改善を行っています。
                    </p>

                    <h2>主な変更内容</h2>

                    <ul>
                        <li>記事編集画面の操作性を改善しました。</li>
                        <li>タグ管理機能を改善しました。</li>
                        <li>画像アップロード時の表示を調整しました。</li>
                        <li>いくつかの不具合を修正しました。</li>
                    </ul>

                    <h2>今後のアップデートについて</h2>

                    <p>
                        これからも、シンプルで使いやすいCMSを目指して、
                        機能追加や使いやすさの改善を続けていきます。
                    </p>

                    <p>今後ともbiscuitをよろしくお願いいたします。</p>
                    HTML,
                'parent_path' => 'news',
                'slug' => 'biscuit-v1-0-release',
                'tags' => ['biscuit', 'アップデート', 'リリース', 'お知らせ'],
            ],
        ];
    }
}
