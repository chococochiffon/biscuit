<?php

namespace Tests\Feature;

use App\Models\PageBuilder;
use App\Models\SinglePage;
use App\Support\Builder\BuilderContent;
use App\Support\Builder\BuilderValidator;
use App\Support\Builder\SchemaMigrator;
use App\Support\Builder\VideoUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VideoBlockTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string|null}>
     */
    public static function urlProvider(): array
    {
        $youtube = 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ';

        return [
            'YouTube の watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', $youtube],
            'YouTube の watch(ほかのパラメーター付き)' => ['https://www.youtube.com/watch?feature=share&v=dQw4w9WgXcQ&t=10', $youtube],
            'YouTube の短縮 URL' => ['https://youtu.be/dQw4w9WgXcQ?si=abc', $youtube],
            'YouTube のショート' => ['https://m.youtube.com/shorts/dQw4w9WgXcQ', $youtube],
            'YouTube の埋め込み' => ['https://www.youtube.com/embed/dQw4w9WgXcQ', $youtube],
            'Vimeo' => ['https://vimeo.com/76979871', 'https://player.vimeo.com/video/76979871'],
            'Vimeo の埋め込み' => ['https://player.vimeo.com/video/76979871?h=abc', 'https://player.vimeo.com/video/76979871'],
            'http' => ['http://www.youtube.com/watch?v=dQw4w9WgXcQ', null],
            '似せたドメイン' => ['https://www.youtube.com.evil.example/watch?v=dQw4w9WgXcQ', null],
            'ほかのサイトのパス' => ['https://evil.example/youtu.be/dQw4w9WgXcQ', null],
            '短すぎる ID' => ['https://www.youtube.com/watch?v=short', null],
            'スクリプト' => ['javascript:alert(1)', null],
            '数字でない Vimeo' => ['https://vimeo.com/abc', null],
        ];
    }

    #[DataProvider('urlProvider')]
    public function test_embed_url_is_built_only_from_youtube_and_vimeo(string $url, ?string $expected): void
    {
        $this->assertSame($expected, VideoUrl::embedUrl($url));
    }

    public function test_validator_accepts_only_supported_video_urls(): void
    {
        $content = fn (mixed $url) => BuilderContent::withDefaultLayout([
            'version' => SchemaMigrator::CURRENT_VERSION,
            'children' => [BuilderContent::node('section', children: [BuilderContent::node('video', ['url' => $url])])],
        ]);

        $this->assertSame([], (new BuilderValidator)->errors($content(null)));
        $this->assertSame([], (new BuilderValidator)->errors($content('https://youtu.be/dQw4w9WgXcQ')));
        $this->assertNotEmpty((new BuilderValidator)->errors($content('https://evil.example/video.mp4')));
        $this->assertNotEmpty((new BuilderValidator)->errors($content('javascript:alert(1)')));
    }

    public function test_resolve_returns_the_embed_url_built_from_the_video_id(): void
    {
        $singlePage = SinglePage::factory()->create(['slug' => 'video-page', 'use_builder' => true]);
        $builder = PageBuilder::factory()->published()->create([
            'single_page_id' => $singlePage->id,
            'draft_content' => BuilderContent::withDefaultLayout([
                'version' => SchemaMigrator::CURRENT_VERSION,
                'children' => [BuilderContent::node('section', children: [
                    BuilderContent::node('video', ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10', 'aspect' => '4x3']),
                    BuilderContent::node('video'),
                ])],
            ]),
        ]);

        $this->getJson(route('api.resolve', ['path' => '/video-page']))
            ->assertOk()
            ->assertJsonPath('data.builder.children.0.children.0.props.aspect', '4x3')
            ->assertJsonPath('data.builder.children.0.children.0.data.embed_url', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ')
            ->assertJsonPath('data.builder.children.0.children.1.data.embed_url', null);

        $this->assertArrayNotHasKey('data', $builder->fresh()->published_content['children'][0]['children'][0]);
    }
}
