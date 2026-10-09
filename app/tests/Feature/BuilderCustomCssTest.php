<?php

namespace Tests\Feature;

use App\Enums\AdministratorRole;
use App\Models\PageBuilder;
use App\Models\PageBuilderComponent;
use App\Models\PageBuilderTheme;
use App\Models\SinglePage;
use App\Support\Builder\BuilderContent;
use App\Support\Builder\BuilderPresenter;
use App\Support\Builder\BuilderValidator;
use App\Support\Builder\CustomCss;
use App\Support\Builder\SchemaMigrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BuilderCustomCssTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private function content(array $children = [], ?string $css = null): array
    {
        return BuilderContent::withDefaultLayout(['version' => SchemaMigrator::CURRENT_VERSION, 'children' => $children, ...($css === null ? [] : ['css' => $css])]);
    }

    public function test_accepts_safe_css(): void
    {
        $css = <<<'CSS'
            /* カードの見た目 */
            .card-box { border-radius: 12px; background: url("/storage/image/builder/a.png") center / cover; }
            .card-box h2 { color: var(--builder-theme-primary); content: "→"; }
            @media (max-width: 767.98px) { .card-box { padding: 8px; } }
            .behavior-note { color: red; }
            CSS;

        $this->assertSame([], CustomCss::errors($css));
        $this->assertSame([], CustomCss::errors(null));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeCssProvider(): array
    {
        return [
            'style から抜け出す' => ['</style><script>alert(1)</script>'],
            'エスケープ' => ['.a { background: u\\72l(https://evil.example/) }'],
            '@import' => ['@import "https://evil.example/a.css";'],
            '外部の url()' => ['.a { background: url(https://evil.example/a.png) }'],
            'プロトコル相対の url()' => ['.a { background: url("//evil.example/a.png") }'],
            'image-set()' => ['.a { background: image-set("https://evil.example/a.png" 1x) }'],
            'expression()' => ['.a { width: expression(alert(1)) }'],
            'behavior' => ['.a { behavior: url(/a.htc) }'],
            'ネストから抜け出す' => ['} body { display: none } .a {'],
            '閉じていない {' => ['.a { color: red;'],
            '閉じていないコメント' => ['.a { color: red } /*'],
        ];
    }

    #[DataProvider('unsafeCssProvider')]
    public function test_rejects_unsafe_css(string $css): void
    {
        $this->assertNotSame([], CustomCss::errors($css));
        $this->assertNotSame([], (new BuilderValidator)->errors($this->content(css: $css)));
    }

    public function test_rejects_too_long_css(): void
    {
        $this->assertNotSame([], CustomCss::errors(str_repeat('a', CustomCss::MAX_LENGTH + 1)));
    }

    public function test_block_classes_are_validated_and_kept(): void
    {
        $validator = new BuilderValidator;
        $heading = BuilderContent::node('heading');
        $heading['classes'] = ['card-title', '_accent'];
        $content = $this->content([BuilderContent::node('section', children: [$heading])], '  .card-title { color: red; }  ');

        $this->assertSame([], $validator->errors($content));
        $normalized = $validator->normalize($content);
        $this->assertSame('.card-title { color: red; }', $normalized['css']);
        $this->assertSame(['card-title', '_accent'], $normalized['children'][0]['children'][0]['classes']);

        foreach ([['1st'], ['a b'], ['a', 'a'], ['a', 'b', 'c', 'd', 'e', 'f'], 'card'] as $classes) {
            $heading['classes'] = $classes;
            $this->assertNotSame([], $validator->errors($this->content([BuilderContent::node('section', children: [$heading])])), json_encode($classes));
        }
    }

    public function test_public_content_includes_css_classes_and_component_css(): void
    {
        PageBuilderTheme::query()->create(['colors' => [], 'custom_css' => '.site { color: red; }']);
        $component = PageBuilderComponent::factory()->published()->create([
            'draft_content' => $this->content([BuilderContent::node('section')], '.component { color: blue; }'),
        ]);
        $heading = BuilderContent::node('heading');
        $heading['classes'] = ['card-title'];

        $public = json_decode(json_encode(BuilderPresenter::forPublic($this->content([
            BuilderContent::node('section', children: [$heading]),
            BuilderContent::node('global', ['component' => $component->id]),
        ], '.page { color: green; }'))), true);

        $this->assertSame('.page { color: green; }', $public['css']);
        $this->assertSame('.site { color: red; }', $public['theme']['css']);
        $this->assertSame(['card-title'], $public['children'][0]['children'][0]['classes']);
        $this->assertSame('.component { color: blue; }', $public['children'][1]['data']['css']);
    }

    public function test_only_super_admins_can_change_page_css_and_classes(): void
    {
        $singlePage = SinglePage::factory()->create();
        $heading = BuilderContent::node('heading');
        $heading['classes'] = ['kept'];
        $builder = PageBuilder::factory()->create([
            'single_page_id' => $singlePage->id,
            'draft_content' => $this->content([BuilderContent::node('section', children: [$heading])], '.kept { color: red; }'),
        ]);

        $this->actingAsAdmin(['role' => AdministratorRole::Admin]);
        $this->getJson(route('admin.json.builder.single-pages.show', $singlePage))->assertOk()->assertJsonPath('can_edit_css', false);

        // ほかの管理者の保存では、CSS とクラス名を今の下書きの値のまま保つ(新しいブロックには付けない)
        $changed = $builder->draft_content;
        $changed['css'] = '.changed { color: blue; }';
        $changed['children'][0]['children'][0]['classes'] = ['changed'];
        $added = BuilderContent::node('text');
        $added['classes'] = ['added'];
        $changed['children'][0]['children'][] = $added;
        $changed = BuilderContent::withDefaultLayout($changed);
        $this->putJson(route('admin.json.builder.single-pages.update', $singlePage), ['content' => $changed, 'updated_at' => $builder->updated_at->toIso8601String()])->assertOk();

        $draft = $builder->fresh()->draft_content;
        $this->assertSame('.kept { color: red; }', $draft['css']);
        $this->assertSame(['kept'], $draft['children'][0]['children'][0]['classes']);
        $this->assertArrayNotHasKey('classes', $draft['children'][0]['children'][1]);

        // スーパー管理者は変えられる
        $this->actingAsSuperAdmin();
        $builder->refresh();
        $this->getJson(route('admin.json.builder.single-pages.show', $singlePage))->assertJsonPath('can_edit_css', true);
        $this->putJson(route('admin.json.builder.single-pages.update', $singlePage), ['content' => $changed, 'updated_at' => $builder->updated_at->toIso8601String()])->assertOk();
        $this->assertSame('.changed { color: blue; }', $builder->fresh()->draft_content['css']);
    }

    public function test_only_super_admins_can_change_the_site_css(): void
    {
        $input = ['colors' => ['primary' => '#0d6efd', 'secondary' => '#6c757d', 'accent' => '#e99540', 'text' => '#212529', 'light' => '#f8f9fa'], 'custom_css' => '.site { color: red; }'];

        $this->actingAsSuperAdmin();
        $this->put(route('admin.builder-theme.update'), $input)->assertRedirect(route('admin.builder-theme.edit'));
        $this->assertSame('.site { color: red; }', PageBuilderTheme::query()->sole()->custom_css);
        $this->from(route('admin.builder-theme.edit'))->put(route('admin.builder-theme.update'), [...$input, 'custom_css' => '@import "x.css";'])->assertSessionHasErrors('custom_css');

        $this->actingAsAdmin(['role' => AdministratorRole::Admin]);
        $this->get(route('admin.builder-theme.edit'))->assertOk()->assertSee('CSS を変えられるのはスーパー管理者だけです。');
        $this->put(route('admin.builder-theme.update'), [...$input, 'custom_css' => '.changed { color: blue; }'])->assertRedirect();
        $this->assertSame('.site { color: red; }', PageBuilderTheme::query()->sole()->custom_css);
    }
}
