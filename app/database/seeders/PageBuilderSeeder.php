<?php

namespace Database\Seeders;

use App\Enums\BuilderPageType;
use App\Models\PageBuilder;
use App\Models\PageBuilderComponent;
use App\Models\PageBuilderTemplate;
use App\Models\PageBuilderVersion;
use App\Models\SinglePage;
use App\Support\Builder\BuilderContent;
use App\Support\Builder\BuilderValidator;
use App\Support\Builder\SchemaMigrator;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PageBuilderSeeder extends Seeder
{
    /**
     * ページビルダーのサンプルの固定ページのスラッグ。
     */
    public const SAMPLE_SLUG = 'builder-sample';

    /**
     * サンプルの固定ページで使う画像(database/seeders/images/ のファイル)。
     */
    private const SAMPLE_IMAGE = 'wide_clean_pastel_cozy_promotional_illustration.png';

    /**
     * インストール直後からページビルダーを試せるよう、次の 2 つを登録する。
     * - ビルダーで作ったサンプルの固定ページ(/builder-sample。use_builder が true で、公開済み)
     * - トップのビルダーの下書き(サイト設定の top_use_builder は false のままなので、トップの表示は変わらない)
     * どちらも登録済みなら登録しない。内容はシーダーでも BuilderValidator を通し、ブロックの定義と食い違えば例外にする。
     * 登録の前に、どのビルダー(論理削除済みを含む)からも参照されていない image/builder の画像を削除する(migrate:refresh --seed のたびに古い画像がたまらないようにする)。
     */
    public function run(BuilderValidator $validator): void
    {
        $this->deleteUnreferencedImages();
        $this->seedSamplePage($validator);
        $this->seedTop($validator);
    }

    private function seedSamplePage(BuilderValidator $validator): void
    {
        $singlePage = SinglePage::query()->firstOrNew(['slug' => self::SAMPLE_SLUG]);

        if (! $singlePage->exists) {
            // DatabaseSeeder はモデルイベントを止めて実行するため、HasPath の代わりに path をここで組み立てる
            $singlePage->forceFill([
                'title' => 'ページビルダーのサンプル',
                'short_sentences' => 'ページビルダーで組み立てたサンプルのページです。',
                'parent_path' => null,
                'top_page_view' => false,
                'link_list_view' => false,
                'use_builder' => true,
                'sort_order' => SinglePage::nextSortOrder(),
                'path' => SinglePage::buildPath(null, self::SAMPLE_SLUG),
            ])->save();
        }

        if ($singlePage->builder()->exists()) {
            return;
        }

        $file = new UploadedFile(DefaultImageSeeder::sourcePath(self::SAMPLE_IMAGE), self::SAMPLE_IMAGE, null, null, true);
        $builder = PageBuilder::newEmpty(BuilderPageType::SinglePage, $singlePage);
        $builder->draft_content = $this->validated($validator, $this->sampleContent(PageBuilder::storeImage($file)));
        $builder->publish();
        $builder->save();
        $builder->recordVersion(null);
    }

    private function seedTop(BuilderValidator $validator): void
    {
        if (PageBuilder::top() !== null) {
            return;
        }

        $builder = PageBuilder::newEmpty(BuilderPageType::Top);
        $builder->draft_content = $this->validated($validator, $this->topContent());
        $builder->save();
    }

    /**
     * サンプルの固定ページの内容(自由配置で、MVP のブロックを一通り使う)。
     *
     * @return array<string, mixed>
     */
    private function sampleContent(string $imagePath): array
    {
        return [
            'version' => SchemaMigrator::CURRENT_VERSION,
            'children' => [
                BuilderContent::node('section', styles: ['paddingTop' => '64px', 'paddingBottom' => '64px', 'backgroundColor' => '#fff7ef', 'textAlign' => 'center'], children: [
                    BuilderContent::node(
                        'heading',
                        ['text' => 'ページビルダーへようこそ', 'level' => 1],
                        ['fontSize' => '40px', 'fontWeight' => '700'],
                        responsive: ['mobile' => ['fontSize' => '28px']],
                        layout: self::at(10, 0, 80),
                    ),
                    BuilderContent::node('text', ['html' => '<p>このページは、ページビルダーで組み立てたサンプルです。管理画面の「ビルダーで編集」から、ブロックをドラッグして好きな位置に置き直せます。</p>'], layout: self::at(15, 72, 70)),
                    BuilderContent::node('button', ['text' => 'トップへ戻る', 'href' => '/'], layout: self::at(35, 160, 30)),
                ]),
                BuilderContent::node('section', styles: ['paddingTop' => '48px', 'paddingBottom' => '48px'], children: [
                    BuilderContent::node('image', ['src' => $imagePath, 'alt' => 'サンプルの画像'], ['borderRadius' => '12px'], layout: self::at(5, 0, 42, 300)),
                    BuilderContent::node('heading', ['text' => 'できること', 'level' => 2], layout: self::at(52, 24, 43)),
                    BuilderContent::node('text', ['html' => '<ul><li>見出し・テキスト・画像・ボタンを好きな位置に置く</li><li>ボックスでまとめて、背景や角丸を付ける</li><li>余白・色・文字の大きさを変える</li><li>スマートフォンだけ位置や文字の大きさを変える</li></ul>'], layout: self::at(52, 80, 43)),
                    BuilderContent::node('divider', styles: ['borderColor' => '#e0d6cc'], layout: self::at(5, 332, 90)),
                    BuilderContent::node('text', ['html' => '<p>編集した内容は下書きとして保存され、「公開」を押すまで公開中のページは変わりません。</p>'], ['textAlign' => 'center'], layout: self::at(10, 364, 80)),
                ]),
                BuilderContent::node('section', styles: ['paddingTop' => '48px', 'paddingBottom' => '64px', 'backgroundColor' => '#f8f9fa'], children: [
                    BuilderContent::node('heading', ['text' => '新着記事', 'level' => 2], ['textAlign' => 'center'], layout: self::at(10, 0, 80)),
                    BuilderContent::node('article-list', ['limit' => 3], layout: self::at(5, 64, 90)),
                ]),
            ],
        ];
    }

    /**
     * トップのビルダーの下書きの内容。
     *
     * @return array<string, mixed>
     */
    private function topContent(): array
    {
        return [
            'version' => SchemaMigrator::CURRENT_VERSION,
            'children' => [
                BuilderContent::node('section', styles: ['paddingTop' => '48px', 'paddingBottom' => '48px', 'textAlign' => 'center'], children: [
                    BuilderContent::node('heading', ['text' => 'トップページのビルダー', 'level' => 2], layout: self::at(10, 0, 80)),
                    BuilderContent::node('text', ['html' => '<p>ここに置いたブロックは、サイト設定で「トップでビルダーを使う」をオンにして公開すると、スライダーの下に表示されます。</p>'], layout: self::at(10, 64, 80)),
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
     * 内容を検証して整形したものを返す。ブロックの定義と食い違っていれば例外にする。
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function validated(BuilderValidator $validator, array $content): array
    {
        $errors = $validator->errors($content);

        if ($errors !== []) {
            throw new RuntimeException('Invalid page builder seed content: '.json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        return $validator->normalize($content);
    }

    /**
     * 保存先ディレクトリの画像のうち、ビルダー(論理削除済みを含む)の編集中・公開中の内容・版・テンプレート・グローバルコンポーネントから参照されていないものを削除する。
     */
    private function deleteUnreferencedImages(): void
    {
        $disk = Storage::disk('public');
        $referencedPaths = PageBuilder::withTrashed()->get()
            ->flatMap(fn (PageBuilder $builder) => [
                ...BuilderContent::imagePaths($builder->draft_content),
                ...BuilderContent::imagePaths($builder->published_content),
            ])
            ->merge(PageBuilderVersion::withTrashed()->get()->flatMap(fn (PageBuilderVersion $version) => BuilderContent::imagePaths($version->content)))
            ->merge(PageBuilderTemplate::withTrashed()->get()->flatMap(fn (PageBuilderTemplate $template) => BuilderContent::imagePaths($template->content)))
            ->merge(PageBuilderComponent::withTrashed()->get()->flatMap(fn (PageBuilderComponent $component) => [
                ...BuilderContent::imagePaths($component->draft_content),
                ...BuilderContent::imagePaths($component->published_content),
            ]))
            ->all();

        $disk->delete(array_values(array_diff($disk->files(PageBuilder::IMAGE_DIRECTORY), $referencedPaths)));
    }
}
