<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\GalleryCategory;
use App\Models\PageBuilderComponent;
use App\Support\Builder\BuilderContent;
use App\Support\Builder\SchemaMigrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PageBuilderTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * @param  list<array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private function content(array $children): array
    {
        return ['version' => SchemaMigrator::CURRENT_VERSION, 'children' => $children];
    }

    private function storeImage(string $path): void
    {
        Storage::disk('public')->put($path, UploadedFile::fake()->image('photo.png', 40, 30)->getContent());
    }

    /**
     * @param  array<string, mixed>  $file
     */
    private function import(array $file, bool $allowsGlobal = true): TestResponse
    {
        return $this->post(route('admin.json.builder.import'), [
            'file' => UploadedFile::fake()->createWithContent('page.json', json_encode($file)),
            'allows_global' => $allowsGlobal ? '1' : '0',
        ], ['Accept' => 'application/json']);
    }

    public function test_export_includes_images_components_and_gallery_categories(): void
    {
        $this->actingAsAdmin();
        $this->storeImage('image/builder/page.png');
        $this->storeImage('image/builder/component.png');
        $category = GalleryCategory::factory()->create(['name' => '風景']);
        $component = PageBuilderComponent::factory()->create([
            'name' => 'お知らせ',
            'published_content' => $this->content([BuilderContent::node('section', children: [BuilderContent::node('image', ['src' => 'image/builder/component.png'])])]),
        ]);
        $content = $this->content([
            BuilderContent::node('section', children: [
                BuilderContent::node('image', ['src' => 'image/builder/page.png']),
                BuilderContent::node('gallery', ['category' => $category->id]),
            ]),
            BuilderContent::node('global', ['component' => $component->id]),
        ]);

        $response = $this->postJson(route('admin.json.builder.export'), ['content' => $content, 'title' => 'トップページ'])
            ->assertOk()
            ->assertJsonPath('format', 'biscuit-page-builder')
            ->assertJsonPath('formatVersion', 1)
            ->assertJsonPath('title', 'トップページ')
            ->assertJsonPath('content.children.0.children.0.props.src', 'image/builder/page.png')
            ->assertJsonPath("components.{$component->id}.name", 'お知らせ')
            ->assertJsonPath("galleryCategories.{$category->id}", '風景');

        $images = $response->json('images');
        $this->assertSame(['image/builder/page.png', 'image/builder/component.png'], array_keys($images));
        $this->assertStringStartsWith('data:image/png;base64,', $images['image/builder/page.png']);

        $log = AuditLog::query()->sole();
        $this->assertSame(AuditAction::Exported, $log->action);
        $this->assertSame(['nodes' => 4, 'images' => 2], $log->metadata);
    }

    public function test_export_rejects_invalid_content(): void
    {
        $this->actingAsAdmin();

        $this->postJson(route('admin.json.builder.export'), ['content' => $this->content([BuilderContent::node('heading')])])
            ->assertUnprocessable();
    }

    public function test_import_restores_images_with_new_paths_and_renews_ids(): void
    {
        $this->actingAsAdmin();
        $this->storeImage('image/builder/page.png');
        $content = $this->content([BuilderContent::node('section', ['backgroundImage' => 'image/builder/page.png'], children: [BuilderContent::node('image', ['src' => 'image/builder/page.png', 'alt' => '写真'])])]);
        $file = $this->postJson(route('admin.json.builder.export'), ['content' => $content])->json();
        // 別のサイトへ移したつもりで、画像を消しておく
        Storage::disk('public')->delete('image/builder/page.png');

        $response = $this->import($file)->assertOk()->assertJsonPath('images', 1)->assertJsonPath('warnings', []);

        $image = $response->json('content.children.0.children.0');
        $this->assertSame('写真', $image['props']['alt']);
        $this->assertNotSame('image/builder/page.png', $image['props']['src']);
        Storage::disk('public')->assertExists($image['props']['src']);
        // 同じ画像は 1 回だけ登録する
        $this->assertSame($image['props']['src'], $response->json('content.children.0.props.backgroundImage'));
        $this->assertCount(1, Storage::disk('public')->allFiles());
        $this->assertNotSame($content['children'][0]['id'], $response->json('content.children.0.id'));
        $this->assertSame(AuditAction::Imported, AuditLog::query()->latest('id')->first()->action);
    }

    public function test_import_links_components_by_name_or_expands_them(): void
    {
        $this->actingAsAdmin();
        $sections = [BuilderContent::node('section', children: [BuilderContent::node('heading', ['text' => 'お知らせの中身'])])];
        $file = [
            'format' => 'biscuit-page-builder',
            'formatVersion' => 1,
            'content' => $this->content([BuilderContent::node('global', ['component' => 7]), BuilderContent::node('global', ['component' => 8])]),
            'components' => [7 => ['name' => 'お知らせ', 'content' => $this->content($sections)], 8 => ['name' => '未公開', 'content' => null]],
        ];
        $local = PageBuilderComponent::factory()->create(['name' => 'お知らせ']);

        $this->import($file)
            ->assertOk()
            ->assertJsonCount(1, 'content.children')
            ->assertJsonPath('content.children.0.type', 'global')
            ->assertJsonPath('content.children.0.props.component', $local->id)
            ->assertJsonPath('warnings', ['グローバルコンポーネント「未公開」は中身がない(未公開)ため、ブロックを外しました。']);

        // コンポーネントのエディタへの読み込み・同じ名前がない場合は、中身のセクションを展開する
        $this->import($file, allowsGlobal: false)
            ->assertOk()
            ->assertJsonPath('content.children.0.type', 'section')
            ->assertJsonPath('content.children.0.children.0.props.text', 'お知らせの中身');

        $local->delete();
        $this->import($file)
            ->assertOk()
            ->assertJsonPath('content.children.0.type', 'section')
            ->assertJsonPath('warnings.0', 'グローバルコンポーネント「お知らせ」が見つからないため、中身をページに展開しました。');
    }

    public function test_import_matches_gallery_categories_and_keeps_existing_images(): void
    {
        $this->actingAsAdmin();
        $this->storeImage('image/builder/local.png');
        $category = GalleryCategory::factory()->create(['name' => '風景']);
        $file = [
            'format' => 'biscuit-page-builder',
            'formatVersion' => 1,
            'content' => $this->content([BuilderContent::node('section', children: [
                BuilderContent::node('gallery', ['category' => 3]),
                BuilderContent::node('gallery', ['category' => 4]),
                BuilderContent::node('image', ['src' => 'image/builder/local.png']),
                BuilderContent::node('image', ['src' => 'image/builder/missing.png']),
            ])]),
            'galleryCategories' => [3 => '風景', 4 => '人物'],
        ];

        $response = $this->import($file)->assertOk()->assertJsonPath('images', 0);

        $this->assertSame($category->id, $response->json('content.children.0.children.0.props.category'));
        $this->assertNull($response->json('content.children.0.children.1.props.category'));
        $this->assertSame('image/builder/local.png', $response->json('content.children.0.children.2.props.src'));
        $this->assertNull($response->json('content.children.0.children.3.props.src'));
        $this->assertSame([
            'ギャラリーの分類「人物」が見つからないため、「すべて」にしました。',
            'ファイルに入っていない画像を外しました。',
        ], $response->json('warnings'));
    }

    public function test_import_rejects_files_that_are_not_valid_exports_without_storing_images(): void
    {
        $this->actingAsAdmin();
        $image = 'data:image/png;base64,'.base64_encode(UploadedFile::fake()->image('photo.png', 10, 10)->getContent());

        $this->post(route('admin.json.builder.import'), ['file' => UploadedFile::fake()->createWithContent('page.json', 'not json')], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'ページビルダーから書き出したファイルではありません。');
        $this->import(['format' => 'biscuit-page-builder', 'formatVersion' => 2, 'content' => $this->content([])])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'このファイルの形式(2)には対応していません。');
        $this->import(['format' => 'biscuit-page-builder', 'formatVersion' => 1, 'content' => ['version' => 99, 'children' => []]])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'このファイルの内容の版には対応していません。');
        $this->import([
            'format' => 'biscuit-page-builder',
            'formatVersion' => 1,
            'content' => $this->content([BuilderContent::node('section', children: [BuilderContent::node('image', ['src' => 'image/builder/a.png'])]), BuilderContent::node('heading')]),
            'images' => ['image/builder/a.png' => $image],
        ])->assertUnprocessable()->assertJsonPath('message', '「見出し」はページの直下に置けません。');

        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_guests_cannot_export_or_import(): void
    {
        $this->postJson(route('admin.json.builder.export'), ['content' => $this->content([])])->assertUnauthorized();
        $this->import(['format' => 'biscuit-page-builder'])->assertUnauthorized();
    }
}
