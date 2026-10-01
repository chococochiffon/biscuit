<?php

namespace Database\Seeders;

use App\Models\PageBuilderTemplate;
use App\Support\Builder\BuilderContent;
use App\Support\Builder\BuilderValidator;
use App\Support\Builder\SchemaMigrator;
use Illuminate\Database\Seeder;
use RuntimeException;

class PageBuilderTemplateSeeder extends Seeder
{
    /**
     * ページビルダーのテンプレート(ランディングページ・会社概要・お問い合わせ)を登録する。
     * テンプレートが 1 件でもあれば(論理削除したものを含む)登録しない(管理者が削除したテンプレートを、シードのやり直しで戻さない)。
     * 内容はシーダーでも BuilderValidator を通し、ブロックの定義と食い違えば例外にする。
     */
    public function run(BuilderValidator $validator): void
    {
        if (PageBuilderTemplate::withTrashed()->exists()) {
            return;
        }

        foreach ($this->templates() as $template) {
            $errors = $validator->errors($template['content']);

            if ($errors !== []) {
                throw new RuntimeException('Invalid page builder template: '.json_encode($errors, JSON_UNESCAPED_UNICODE));
            }

            PageBuilderTemplate::create([
                ...$template,
                'schema_version' => SchemaMigrator::CURRENT_VERSION,
                'content' => $validator->normalize($template['content']),
            ]);
        }
    }

    /**
     * @return list<array{name: string, description: string, content: array<string, mixed>}>
     */
    private function templates(): array
    {
        return [
            [
                'name' => 'ランディングページ',
                'description' => 'サービスや商品を 1 ページで紹介します。大きな見出し・3 つの特徴・問い合わせへの案内。',
                'content' => $this->content([
                    BuilderContent::node('section', styles: ['paddingTop' => '96px', 'paddingBottom' => '96px', 'backgroundColor' => '#f5f7fb', 'textAlign' => 'center'], children: [
                        BuilderContent::node('container', styles: ['maxWidth' => '960px'], children: [
                            BuilderContent::node('heading', ['text' => 'サービスの名前', 'level' => 1], ['fontSize' => '48px', 'fontWeight' => '700'], responsive: ['mobile' => ['fontSize' => '32px']]),
                            BuilderContent::node('text', ['html' => '<p>サービスの魅力を、ひとことで伝える説明をここに書きます。</p>']),
                            BuilderContent::node('spacer', ['height' => 16]),
                            BuilderContent::node('button', ['text' => '詳しく見る', 'href' => '#']),
                        ]),
                    ]),
                    BuilderContent::node('section', styles: ['paddingTop' => '64px', 'paddingBottom' => '64px'], children: [
                        BuilderContent::node('container', children: [
                            BuilderContent::node('heading', ['text' => '特徴', 'level' => 2], ['textAlign' => 'center']),
                            BuilderContent::node('spacer', ['height' => 16]),
                            BuilderContent::node('row', ['gap' => 4], children: array_map(
                                fn (int $number) => BuilderContent::node('column', ['span' => 4], children: [
                                    BuilderContent::node('heading', ['text' => "特徴 {$number}", 'level' => 3]),
                                    BuilderContent::node('text', ['html' => '<p>特徴の説明をここに書きます。</p>']),
                                ]),
                                [1, 2, 3],
                            )),
                        ]),
                    ]),
                    BuilderContent::node('section', styles: ['paddingTop' => '64px', 'paddingBottom' => '64px', 'backgroundColor' => '#343a40', 'color' => '#ffffff', 'textAlign' => 'center'], children: [
                        BuilderContent::node('container', children: [
                            BuilderContent::node('heading', ['text' => 'まずはお気軽にご相談ください', 'level' => 2]),
                            BuilderContent::node('spacer', ['height' => 16]),
                            BuilderContent::node('button', ['text' => 'お問い合わせ', 'href' => '#']),
                        ]),
                    ]),
                ]),
            ],
            [
                'name' => '会社概要',
                'description' => '会社の基本情報を項目ごとに並べ、代表のメッセージを添えます。',
                'content' => $this->content([
                    BuilderContent::node('section', styles: ['paddingTop' => '64px', 'paddingBottom' => '48px'], children: [
                        BuilderContent::node('container', styles: ['maxWidth' => '960px'], children: [
                            BuilderContent::node('heading', ['text' => '会社概要', 'level' => 1]),
                            BuilderContent::node('text', ['html' => '<p>会社の紹介文をここに書きます。</p>']),
                            BuilderContent::node('divider'),
                            ...array_map(
                                fn (array $item) => BuilderContent::node('row', styles: ['marginBottom' => '8px'], children: [
                                    BuilderContent::node('column', ['span' => 3, 'spanMobile' => 12], children: [
                                        BuilderContent::node('heading', ['text' => $item[0], 'level' => 3], ['fontSize' => '1rem', 'fontWeight' => '700']),
                                    ]),
                                    BuilderContent::node('column', ['span' => 9, 'spanMobile' => 12], children: [
                                        BuilderContent::node('text', ['html' => "<p>{$item[1]}</p>"]),
                                    ]),
                                ]),
                                [['会社名', '株式会社サンプル'], ['所在地', '東京都〇〇区〇〇 1-2-3'], ['設立', '2020 年 4 月'], ['事業内容', 'Web サイトの制作・運用']],
                            ),
                        ]),
                    ]),
                    BuilderContent::node('section', styles: ['paddingTop' => '48px', 'paddingBottom' => '64px', 'backgroundColor' => '#f8f9fa'], children: [
                        BuilderContent::node('container', styles: ['maxWidth' => '960px'], children: [
                            BuilderContent::node('heading', ['text' => '代表メッセージ', 'level' => 2]),
                            BuilderContent::node('row', ['gap' => 4], children: [
                                BuilderContent::node('column', ['span' => 4], children: [
                                    BuilderContent::node('image', ['alt' => '代表の写真']),
                                ]),
                                BuilderContent::node('column', ['span' => 8], children: [
                                    BuilderContent::node('text', ['html' => '<p>代表からのメッセージをここに書きます。</p>']),
                                ]),
                            ]),
                        ]),
                    ]),
                ]),
            ],
            [
                'name' => 'お問い合わせ',
                'description' => 'メール・電話での問い合わせ先を案内します。',
                'content' => $this->content([
                    BuilderContent::node('section', styles: ['paddingTop' => '64px', 'paddingBottom' => '64px', 'textAlign' => 'center'], children: [
                        BuilderContent::node('container', styles: ['maxWidth' => '720px'], children: [
                            BuilderContent::node('heading', ['text' => 'お問い合わせ', 'level' => 1]),
                            BuilderContent::node('text', ['html' => '<p>ご質問・ご相談は、下記の連絡先からお気軽にお問い合わせください。</p>']),
                            BuilderContent::node('spacer', ['height' => 24]),
                            BuilderContent::node('button', ['text' => 'メールで問い合わせる', 'href' => 'mailto:info@example.com']),
                            BuilderContent::node('spacer', ['height' => 24]),
                            BuilderContent::node('text', ['html' => '<p>お電話: 00-0000-0000(平日 10:00〜18:00)</p>']),
                            BuilderContent::node('button', ['text' => '電話をかける', 'href' => 'tel:0000000000', 'variant' => 'outline-primary']),
                        ]),
                    ]),
                ]),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $children
     * @return array{version: int, children: list<array<string, mixed>>}
     */
    private function content(array $children): array
    {
        return ['version' => SchemaMigrator::CURRENT_VERSION, 'children' => $children];
    }
}
