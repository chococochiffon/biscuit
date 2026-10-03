<?php

namespace Tests\Feature;

use App\Enums\CallContentPlace;
use App\Models\Article;
use App\Models\CallContent;
use App\Models\GalleryImage;
use App\Models\PageBuilder;
use App\Models\PageBuilderTemplate;
use App\Models\SinglePage;
use App\Models\SiteSetting;
use App\Models\TopSliderImage;
use Database\Seeders\GallerySeeder;
use Database\Seeders\TopSliderImageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_all_initial_data_including_images(): void
    {
        Storage::fake('public');

        // DatabaseSeeder は WithoutModelEvents で各シーダーを実行するため、イベント頼みの処理が漏れていないかを通しで確認する
        $this->seed();

        Storage::disk('public')->assertExists([
            Article::DEFAULT_THUMBNAIL_PATH,
            SiteSetting::DEFAULT_SITE_ICON_PATH,
            SiteSetting::DEFAULT_SITE_IMAGE_PATH,
        ]);

        $sliderImages = TopSliderImage::query()->ordered()->get();
        $this->assertCount(3, $sliderImages);

        foreach ($sliderImages as $sliderImage) {
            $this->assertSame([1920, 1080], array_slice(getimagesizefromstring(Storage::disk('public')->get($sliderImage->top_image)), 0, 2));
        }

        $galleryImages = GalleryImage::query()->with('category')->ordered()->get();
        $this->assertCount(4, $galleryImages);
        $this->assertSame(['昼のワークスペース', '夕方・夜のワークスペース', '昼のワークスペース', '夕方・夜のワークスペース'], $galleryImages->pluck('category.name')->all());
        Storage::disk('public')->assertExists($galleryImages->pluck('image')->all());

        $this->assertSame(['/information/about', '/builder-sample'], SinglePage::query()->orderBy('id')->pluck('path')->all());

        // ページビルダーのサンプルの固定ページは公開済み、トップのビルダーは下書きだけ(トップの表示は変わらない)
        $this->assertTrue(SinglePage::query()->where('slug', 'builder-sample')->sole()->builder->isPublished());
        $this->assertFalse(PageBuilder::top()->isPublished());
        $this->assertSame(3, PageBuilderTemplate::query()->count());
        $this->assertSame(3, Article::query()->whereNotNull('path')->count());

        $this->getJson(route('api.resolve', ['path' => '/news/biscuit-v1-0-release']))
            ->assertOk()
            ->assertJsonPath('type', 'article');

        // レイアウトの初期値(記事ページだけ右サイドバーに新着記事)が公開側の API で取得できる
        $this->getJson(route('api.layout.show'))
            ->assertOk()
            ->assertJsonPath('data.pages.article.sidebar_position', 'right')
            ->assertJsonPath('data.regions.header.1.block_type', 'nav_menu')
            ->assertJsonPath('data.regions.sidebar.0.call_content.call_type', 'link_list');
    }

    public function test_seeded_call_contents_use_allowed_combinations(): void
    {
        Storage::fake('public');

        $this->seed();

        $callContents = CallContent::query()->with('contentModelRelation')->ordered()->get();

        // 管理画面で選べない組み合わせ(表示箇所 × データ種別 × 呼び出し方)を初期データに入れていないこと
        foreach ($callContents as $callContent) {
            $this->assertTrue(
                $callContent->call_type->supports($callContent->contentModelRelation->model_name, $callContent->place),
                "{$callContent->call_name} の組み合わせは選択できません。"
            );
        }

        $this->assertSame(
            ['SinglePage', 'UserSkill', 'ArticleArchive', 'GalleryTileList', 'QuestionAnswerAccordion'],
            $callContents->where('place', CallContentPlace::Top)->pluck('call_name')->values()->all()
        );
        // ヘッダー・フッターの部品はレイアウト管理(LayoutSeeder)で登録し、呼び出しコンテンツには登録しない
        $this->assertSame([], $callContents->where('place', CallContentPlace::Layout)->pluck('call_name')->values()->all());
    }

    public function test_top_slider_images_are_not_duplicated_when_seeding_again(): void
    {
        Storage::fake('public');

        $this->seed(TopSliderImageSeeder::class);
        $this->seed(TopSliderImageSeeder::class);

        $this->assertSame(3, TopSliderImage::count());
    }

    public function test_top_slider_image_seeder_deletes_only_unreferenced_image_files(): void
    {
        Storage::fake('public');
        $disk = Storage::disk('public');
        $disk->put('image/top_image/orphan.png', 'orphan');
        $disk->put('image/top_image/active.png', 'active');
        $disk->put('image/top_image/trashed.png', 'trashed');
        $disk->put('image/other.png', 'other');
        TopSliderImage::factory()->create(['top_image' => 'image/top_image/active.png']);
        TopSliderImage::factory()->create(['top_image' => 'image/top_image/trashed.png'])->delete();

        $this->seed(TopSliderImageSeeder::class);

        $disk->assertMissing('image/top_image/orphan.png');
        $disk->assertExists(['image/top_image/active.png', 'image/top_image/trashed.png', 'image/other.png']);
        // 登録済みのスライダー画像があるため、サンプルは追加しない
        $this->assertCount(2, $disk->files('image/top_image'));
    }

    public function test_top_slider_image_seeder_replaces_old_image_files_after_database_refresh(): void
    {
        Storage::fake('public');
        $disk = Storage::disk('public');
        // migrate:refresh 後と同じく、レコードはなく前回のファイルだけが残っている状態
        $disk->put('image/top_image/old.png', 'old');

        $this->seed(TopSliderImageSeeder::class);

        $disk->assertMissing('image/top_image/old.png');
        $this->assertEqualsCanonicalizing(
            TopSliderImage::query()->pluck('top_image')->all(),
            $disk->files('image/top_image')
        );
    }

    public function test_gallery_seeder_does_not_duplicate_and_deletes_unreferenced_image_files(): void
    {
        Storage::fake('public');
        $disk = Storage::disk('public');
        // migrate:refresh 後と同じく、レコードはなく前回のファイルだけが残っている状態
        $disk->put(GalleryImage::IMAGE_DIRECTORY.'/old.png', 'old');

        $this->seed(GallerySeeder::class);
        $this->seed(GallerySeeder::class);

        $disk->assertMissing(GalleryImage::IMAGE_DIRECTORY.'/old.png');
        $this->assertSame(4, GalleryImage::count());
        $this->assertEqualsCanonicalizing(GalleryImage::query()->pluck('image')->all(), $disk->files(GalleryImage::IMAGE_DIRECTORY));
    }
}
