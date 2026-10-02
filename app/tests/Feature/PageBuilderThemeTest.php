<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\PageBuilder;
use App\Models\PageBuilderTheme;
use App\Models\SinglePage;
use App\Support\Builder\BuilderContent;
use App\Support\Builder\BuilderPresenter;
use App\Support\Builder\BuilderValidator;
use App\Support\Builder\SchemaMigrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageBuilderThemeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function themeInput(array $overrides = []): array
    {
        return [
            'colors' => ['primary' => '#E99540', 'secondary' => '#555555', 'accent' => '#ff6800', 'text' => '#222222', 'light' => '#fff7ef'],
            'heading_font' => 'noto-serif-jp',
            'body_font' => '',
            ...$overrides,
        ];
    }

    public function test_guests_cannot_edit_the_theme(): void
    {
        $this->get(route('admin.builder-theme.edit'))->assertRedirect(route('admin.login'));
        $this->put(route('admin.builder-theme.update'), $this->themeInput())->assertRedirect(route('admin.login'));
        $this->assertSame(0, PageBuilderTheme::query()->count());
    }

    public function test_edit_shows_the_default_theme_before_it_is_saved(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.builder-theme.edit'))
            ->assertOk()
            ->assertSee('value="#0d6efd"', false)
            ->assertSee('Noto Serif JP(明朝)');
    }

    public function test_update_creates_the_theme_once_and_then_updates_it(): void
    {
        $this->actingAsAdmin();

        $this->put(route('admin.builder-theme.update'), $this->themeInput())->assertRedirect(route('admin.builder-theme.edit'));
        $this->put(route('admin.builder-theme.update'), $this->themeInput(['body_font' => 'noto-sans-jp']))->assertRedirect(route('admin.builder-theme.edit'));

        $theme = PageBuilderTheme::query()->sole();
        $this->assertSame('#e99540', $theme->colors['primary']);
        $this->assertSame('noto-serif-jp', $theme->heading_font);
        $this->assertSame('noto-sans-jp', $theme->body_font);
        $this->assertSame([AuditAction::Created, AuditAction::Updated], AuditLog::query()->orderBy('id')->pluck('action')->all());
    }

    public function test_update_rejects_invalid_colors_and_fonts(): void
    {
        $this->actingAsAdmin();

        $this->from(route('admin.builder-theme.edit'))
            ->put(route('admin.builder-theme.update'), $this->themeInput(['colors' => ['primary' => 'red'], 'heading_font' => 'comic-sans']))
            ->assertSessionHasErrors(['colors.primary', 'colors.secondary', 'heading_font']);

        $this->assertSame(0, PageBuilderTheme::query()->count());
    }

    public function test_block_colors_accept_theme_colors(): void
    {
        $heading = BuilderContent::node('heading', styles: ['color' => 'theme:primary', 'marginTop' => '8px']);
        $content = ['version' => SchemaMigrator::CURRENT_VERSION, 'children' => [BuilderContent::node('section', styles: ['backgroundColor' => 'theme:light'], children: [$heading])]];

        $this->assertSame([], (new BuilderValidator)->errors($content));

        $content['children'][0]['styles']['backgroundColor'] = 'theme:unknown';
        $this->assertCount(1, (new BuilderValidator)->errors($content));
    }

    public function test_public_content_and_the_editor_include_the_theme(): void
    {
        $this->actingAsAdmin();
        PageBuilderTheme::query()->create(['colors' => ['primary' => '#e99540'], 'heading_font' => 'noto-serif-jp', 'body_font' => null]);
        $singlePage = SinglePage::factory()->create();
        PageBuilder::factory()->for($singlePage)->create();

        $public = BuilderPresenter::forPublic(BuilderContent::empty());
        // 登録していない色は既定値で補う
        $this->assertSame(['primary' => '#e99540', 'secondary' => '#6c757d', 'accent' => '#e99540', 'text' => '#212529', 'light' => '#f8f9fa'], $public['theme']['colors']);
        $this->assertSame("'Noto Serif JP', serif", $public['theme']['fonts']['heading']['family']);
        $this->assertSame('https://fonts.googleapis.com/css2?family=Noto+Serif+JP:wght@400;700&display=swap', $public['theme']['fonts']['heading']['href']);
        $this->assertNull($public['theme']['fonts']['body']);

        $this->getJson(route('admin.json.builder.single-pages.show', $singlePage))
            ->assertOk()
            ->assertJsonPath('theme.colors.primary', '#e99540')
            ->assertJsonPath('theme.fonts.heading.key', 'noto-serif-jp');
    }
}
