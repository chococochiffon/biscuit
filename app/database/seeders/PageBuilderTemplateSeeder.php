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
     * 自由配置のテンプレート(ブロックは座標で置く。at() はデスクトップの位置で、x・w は %、y・h は px)。
     *
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
                        BuilderContent::node('heading', ['text' => 'サービスの名前', 'level' => 1], ['fontSize' => '48px', 'fontWeight' => '700'], responsive: ['mobile' => ['fontSize' => '32px']], layout: self::at(10, 0, 80)),
                        BuilderContent::node('text', ['html' => '<p>サービスの魅力を、ひとことで伝える説明をここに書きます。</p>'], layout: self::at(15, 88, 70)),
                        BuilderContent::node('button', ['text' => '詳しく見る', 'href' => '#'], layout: self::at(35, 152, 30)),
                    ]),
                    BuilderContent::node('section', styles: ['paddingTop' => '64px', 'paddingBottom' => '64px'], children: [
                        BuilderContent::node('heading', ['text' => '特徴', 'level' => 2], ['textAlign' => 'center'], layout: self::at(10, 0, 80)),
                        ...array_map(
                            fn (int $index) => BuilderContent::node('box', styles: ['backgroundColor' => '#f5f7fb', 'borderRadius' => '8px'], layout: self::at([5, 36.67, 68.33][$index], 72, 26.67, 200), children: [
                                BuilderContent::node('heading', ['text' => '特徴 '.($index + 1), 'level' => 3], ['fontSize' => '20px'], layout: self::at(8, 24, 84)),
                                BuilderContent::node('text', ['html' => '<p>特徴の説明をここに書きます。</p>'], layout: self::at(8, 72, 84)),
                            ]),
                            [0, 1, 2],
                        ),
                    ]),
                    BuilderContent::node('section', styles: ['paddingTop' => '64px', 'paddingBottom' => '64px', 'backgroundColor' => '#343a40', 'color' => '#ffffff', 'textAlign' => 'center'], children: [
                        BuilderContent::node('heading', ['text' => 'まずはお気軽にご相談ください', 'level' => 2], layout: self::at(10, 0, 80)),
                        BuilderContent::node('button', ['text' => 'お問い合わせ', 'href' => '#'], layout: self::at(35, 72, 30)),
                    ]),
                ]),
            ],
            [
                'name' => '会社概要',
                'description' => '会社の基本情報を項目ごとに並べ、代表のメッセージを添えます。',
                'content' => $this->content([
                    BuilderContent::node('section', styles: ['paddingTop' => '64px', 'paddingBottom' => '48px'], children: [
                        BuilderContent::node('heading', ['text' => '会社概要', 'level' => 1], layout: self::at(10, 0, 80)),
                        BuilderContent::node('text', ['html' => '<p>会社の紹介文をここに書きます。</p>'], layout: self::at(10, 64, 80)),
                        BuilderContent::node('divider', layout: self::at(10, 120, 80)),
                        ...array_merge(...array_map(
                            fn (int $index, array $item) => [
                                BuilderContent::node('heading', ['text' => $item[0], 'level' => 3], ['fontSize' => '1rem', 'fontWeight' => '700'], layout: self::at(10, 144 + $index * 48, 20)),
                                BuilderContent::node('text', ['html' => "<p>{$item[1]}</p>"], layout: self::at(32, 144 + $index * 48, 58)),
                            ],
                            [0, 1, 2, 3],
                            [['会社名', '株式会社サンプル'], ['所在地', '東京都〇〇区〇〇 1-2-3'], ['設立', '2020 年 4 月'], ['事業内容', 'Web サイトの制作・運用']],
                        )),
                    ]),
                    BuilderContent::node('section', styles: ['paddingTop' => '48px', 'paddingBottom' => '64px', 'backgroundColor' => '#f8f9fa'], children: [
                        BuilderContent::node('heading', ['text' => '代表メッセージ', 'level' => 2], layout: self::at(10, 0, 80)),
                        BuilderContent::node('image', ['alt' => '代表の写真'], layout: self::at(10, 64, 25, 240)),
                        BuilderContent::node('text', ['html' => '<p>代表からのメッセージをここに書きます。</p>'], layout: self::at(40, 64, 50)),
                    ]),
                ]),
            ],
            [
                'name' => 'お問い合わせ',
                'description' => 'メール・電話での問い合わせ先を案内します。',
                'content' => $this->content([
                    BuilderContent::node('section', styles: ['paddingTop' => '64px', 'paddingBottom' => '64px', 'textAlign' => 'center'], children: [
                        BuilderContent::node('heading', ['text' => 'お問い合わせ', 'level' => 1], layout: self::at(20, 0, 60)),
                        BuilderContent::node('text', ['html' => '<p>ご質問・ご相談は、下記の連絡先からお気軽にお問い合わせください。</p>'], layout: self::at(20, 72, 60)),
                        BuilderContent::node('button', ['text' => 'メールで問い合わせる', 'href' => 'mailto:info@example.com'], layout: self::at(30, 144, 40)),
                        BuilderContent::node('text', ['html' => '<p>お電話: 00-0000-0000(平日 10:00〜18:00)</p>'], layout: self::at(20, 224, 60)),
                        BuilderContent::node('button', ['text' => '電話をかける', 'href' => 'tel:0000000000', 'variant' => 'outline-primary'], layout: self::at(30, 272, 40)),
                    ]),
                ]),
            ],
        ];
    }

    /**
     * 自由配置のデスクトップの位置(x・w は %、y・h は px)。
     *
     * @return array{desktop: array{x: int|float, y: int, w: int|float, h?: int}}
     */
    private static function at(int|float $x, int $y, int|float $w, ?int $h = null): array
    {
        return ['desktop' => ['x' => $x, 'y' => $y, 'w' => $w, ...($h === null ? [] : ['h' => $h])]];
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
