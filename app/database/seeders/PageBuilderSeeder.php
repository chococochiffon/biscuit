<?php

namespace Database\Seeders;

use App\Enums\BuilderPageType;
use App\Models\PageBuilder;
use App\Models\PageBuilderTemplate;
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
     * サンプルの固定ページの内容(MVP のブロックを一通り使う)。
     *
     * @return array<string, mixed>
     */
    private function sampleContent(string $imagePath): array
    {
        return [
            'version' => SchemaMigrator::CURRENT_VERSION,
            'children' => [
                BuilderContent::node('section', styles: ['paddingTop' => '64px', 'paddingBottom' => '64px', 'backgroundColor' => '#fff7ef', 'textAlign' => 'center'], children: [
                    BuilderContent::node('container', styles: ['maxWidth' => '960px'], children: [
                        BuilderContent::node(
                            'heading',
                            ['text' => 'ページビルダーへようこそ', 'level' => 1],
                            ['fontSize' => '40px', 'fontWeight' => '700'],
                            responsive: ['mobile' => ['fontSize' => '28px']],
                        ),
                        BuilderContent::node('text', ['html' => '<p>このページは、ページビルダーで組み立てたサンプルです。管理画面の「ビルダーで編集」から、ブロックをドラッグして自由に組み立て直せます。</p>']),
                        BuilderContent::node('spacer', ['height' => 16]),
                        BuilderContent::node('button', ['text' => 'トップへ戻る', 'href' => '/']),
                    ]),
                ]),
                BuilderContent::node('section', styles: ['paddingTop' => '48px', 'paddingBottom' => '48px'], children: [
                    BuilderContent::node('container', children: [
                        BuilderContent::node('row', ['gap' => 4], children: [
                            BuilderContent::node('column', ['span' => 6, 'spanMobile' => 12], children: [
                                BuilderContent::node('image', ['src' => $imagePath, 'alt' => 'サンプルの画像'], ['borderRadius' => '12px']),
                            ]),
                            BuilderContent::node('column', ['span' => 6, 'spanMobile' => 12], children: [
                                BuilderContent::node('heading', ['text' => 'できること', 'level' => 2]),
                                BuilderContent::node('text', ['html' => '<ul><li>見出し・テキスト・画像・ボタンを並べる</li><li>行とカラムで横に並べる</li><li>余白・色・文字の大きさを変える</li><li>スマートフォンだけ文字の大きさを変える</li></ul>']),
                            ]),
                        ]),
                        BuilderContent::node('divider', styles: ['marginTop' => '32px', 'marginBottom' => '32px', 'borderColor' => '#e0d6cc']),
                        BuilderContent::node('text', ['html' => '<p>編集した内容は下書きとして保存され、「公開」を押すまで公開中のページは変わりません。</p>'], ['textAlign' => 'center']),
                    ]),
                ]),
                BuilderContent::node('section', styles: ['paddingTop' => '48px', 'paddingBottom' => '64px', 'backgroundColor' => '#f8f9fa'], children: [
                    BuilderContent::node('container', children: [
                        BuilderContent::node('heading', ['text' => '新着記事', 'level' => 2], ['textAlign' => 'center']),
                        BuilderContent::node('spacer', ['height' => 16]),
                        BuilderContent::node('article-list', ['limit' => 3]),
                    ]),
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
                    BuilderContent::node('container', children: [
                        BuilderContent::node('heading', ['text' => 'トップページのビルダー', 'level' => 2]),
                        BuilderContent::node('text', ['html' => '<p>ここに置いたブロックは、サイト設定で「トップでビルダーを使う」をオンにして公開すると、スライダーの下に表示されます。</p>']),
                    ]),
                ]),
            ],
        ];
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
     * 保存先ディレクトリの画像のうち、ビルダー(論理削除済みを含む)の編集中・公開中の内容とテンプレートから参照されていないものを削除する。
     */
    private function deleteUnreferencedImages(): void
    {
        $disk = Storage::disk('public');
        $referencedPaths = PageBuilder::withTrashed()->get()
            ->flatMap(fn (PageBuilder $builder) => [
                ...BuilderContent::imagePaths($builder->draft_content),
                ...BuilderContent::imagePaths($builder->published_content),
            ])
            ->merge(PageBuilderTemplate::withTrashed()->get()->flatMap(fn (PageBuilderTemplate $template) => BuilderContent::imagePaths($template->content)))
            ->all();

        $disk->delete(array_values(array_diff($disk->files(PageBuilder::IMAGE_DIRECTORY), $referencedPaths)));
    }
}
