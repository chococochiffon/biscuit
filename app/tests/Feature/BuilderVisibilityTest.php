<?php

namespace Tests\Feature;

use App\Models\PageBuilder;
use App\Models\PageBuilderComponent;
use App\Models\SinglePage;
use App\Support\Builder\BuilderContent;
use App\Support\Builder\BuilderPresenter;
use App\Support\Builder\BuilderValidator;
use App\Support\Builder\SchemaMigrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BuilderVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * セクションの中に、表示条件を付けた見出しを 1 つ置いた内容。
     *
     * @param  array<string, mixed>  $visibility
     * @return array<string, mixed>
     */
    private static function content(array $visibility, string $text = '見出し'): array
    {
        $heading = BuilderContent::node('heading', ['text' => $text]);
        $heading['visibility'] = $visibility;

        return [
            'version' => SchemaMigrator::CURRENT_VERSION,
            'children' => [BuilderContent::node('section', children: [$heading])],
        ];
    }

    public function test_accepts_valid_visibility(): void
    {
        $validator = new BuilderValidator;

        $this->assertSame([], $validator->errors(self::content([])));
        $this->assertSame([], $validator->errors(self::content(['hideOn' => ['mobile', 'tablet']])));
        $this->assertSame([], $validator->errors(self::content(['startAt' => '2026-10-01T09:00', 'endAt' => '2026-10-31T18:00'])));
        $this->assertSame([], $validator->errors(self::content(['startAt' => null, 'endAt' => '2026-10-31T18:00'])));
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidVisibilityProvider(): array
    {
        return [
            '配列でない' => ['mobile', '「見出し」の表示条件の形式が正しくありません。'],
            '知らない項目' => [['showOn' => ['mobile']], '「見出し」の表示条件に「showOn」という項目はありません。'],
            '知らない端末' => [['hideOn' => ['watch']], '「見出し」の表示しない端末の値が正しくありません。'],
            '端末の重複' => [['hideOn' => ['mobile', 'mobile']], '「見出し」の表示しない端末の値が正しくありません。'],
            'すべての端末' => [['hideOn' => ['desktop', 'tablet', 'mobile']], '「見出し」を、すべての端末で表示しないようにはできません。'],
            '開始の形式' => [['startAt' => '2026/10/01 09:00'], '「見出し」の表示を始める日時が正しくありません。'],
            'ありえない日時' => [['endAt' => '2026-02-30T09:00'], '「見出し」の表示を終える日時が正しくありません。'],
            '終了が開始より前' => [['startAt' => '2026-10-02T09:00', 'endAt' => '2026-10-02T09:00'], '「見出し」の表示を終える日時は、表示を始める日時より後にしてください。'],
        ];
    }

    #[DataProvider('invalidVisibilityProvider')]
    public function test_rejects_invalid_visibility(mixed $visibility, string $message): void
    {
        $content = self::content([]);
        $content['children'][0]['children'][0]['visibility'] = $visibility;

        $errors = (new BuilderValidator)->errors($content);

        $this->assertSame([['node' => $content['children'][0]['children'][0]['id'], 'message' => $message]], $errors);
    }

    public function test_normalize_keeps_only_given_conditions(): void
    {
        $validator = new BuilderValidator;

        $empty = $validator->normalize(self::content(['hideOn' => [], 'startAt' => null]));
        $this->assertArrayNotHasKey('visibility', $empty['children'][0]['children'][0]);

        $given = $validator->normalize(self::content(['hideOn' => ['mobile', 'desktop'], 'endAt' => '2026-10-31T18:00']));
        $this->assertSame(['hideOn' => ['desktop', 'mobile'], 'endAt' => '2026-10-31T18:00'], $given['children'][0]['children'][0]['visibility']);
    }

    public function test_public_content_drops_blocks_outside_the_period_and_keeps_only_devices(): void
    {
        $this->travelTo('2026-10-02 12:00:00');
        $content = self::content(['hideOn' => ['mobile'], 'startAt' => '2026-10-02T12:00', 'endAt' => '2026-10-03T00:00'], '期間中');
        $section = $content['children'][0];
        $future = BuilderContent::node('text');
        $future['visibility'] = ['startAt' => '2026-10-02T12:01'];
        $past = BuilderContent::node('section', children: [BuilderContent::node('heading')]);
        $past['visibility'] = ['endAt' => '2026-10-02T12:00'];
        $section['children'][] = $future;
        $content['children'] = [$section, $past];

        $public = json_decode(json_encode(BuilderPresenter::forPublic($content)), true);
        $editor = json_decode(json_encode(BuilderPresenter::forEditor($content)), true);

        // 公開側: 期間の外のブロック(子ごと)を取り除き、表示条件は端末だけを返す
        $this->assertCount(1, $public['children']);
        $this->assertCount(1, $public['children'][0]['children']);
        $this->assertSame('期間中', $public['children'][0]['children'][0]['props']['text']);
        $this->assertSame(['hideOn' => ['mobile']], $public['children'][0]['children'][0]['visibility']);

        // エディタ: すべてのブロックと表示条件をそのまま返す
        $this->assertCount(2, $editor['children']);
        $this->assertCount(2, $editor['children'][0]['children']);
        $this->assertSame(['startAt' => '2026-10-02T12:01'], $editor['children'][0]['children'][1]['visibility']);
    }

    public function test_resolve_api_hides_scheduled_blocks_until_they_start(): void
    {
        $singlePage = SinglePage::factory()->create(['slug' => 'campaign', 'use_builder' => true]);
        $content = (new BuilderValidator)->normalize(self::content(['startAt' => '2026-10-10T00:00'], 'キャンペーン'));
        PageBuilder::factory()->for($singlePage)->create(['draft_content' => $content, 'published_content' => $content, 'published_at' => now()]);

        $this->travelTo('2026-10-09 23:59:00');
        $this->getJson('/api/resolve?path='.$singlePage->path)->assertOk()->assertJsonCount(0, 'data.builder.children.0.children');

        $this->travelTo('2026-10-10 00:00:00');
        $this->getJson('/api/resolve?path='.$singlePage->path)->assertOk()->assertJsonPath('data.builder.children.0.children.0.props.text', 'キャンペーン');
    }

    public function test_preview_shows_blocks_outside_the_period_including_component_contents(): void
    {
        $this->travelTo('2026-10-09 12:00:00');
        $future = BuilderContent::node('heading', ['text' => 'これから始まる見出し']);
        $future['visibility'] = ['startAt' => '2026-10-10T00:00'];
        $component = PageBuilderComponent::factory()->published()->create([
            'draft_content' => ['version' => SchemaMigrator::CURRENT_VERSION, 'children' => [BuilderContent::node('section', children: [$future])]],
        ]);
        $content = (new BuilderValidator)->normalize(['version' => SchemaMigrator::CURRENT_VERSION, 'children' => [
            BuilderContent::node('section', children: [$future]),
            BuilderContent::node('global', ['component' => $component->id]),
        ]]);
        $builder = PageBuilder::factory()->top()->create(['draft_content' => $content]);
        $url = URL::temporarySignedRoute('api.builder-previews.show', now()->addMinutes(30), ['pageBuilder' => $builder->id], absolute: false);

        // プレビューでは期間の外のブロックも返す(グローバルコンポーネントの中身も)
        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('builder.children.0.children.0.props.text', 'これから始まる見出し')
            ->assertJsonPath('builder.children.1.data.children.0.children.0.props.text', 'これから始まる見出し');

        // 公開側では取り除いたまま(プレビューのあとに公開側を返しても、切り替えは残らない)
        $public = json_decode(json_encode(BuilderPresenter::forPublic($content)), true);
        $this->assertSame([], $public['children'][0]['children']);
        $this->assertSame([], $public['children'][1]['data']['children'][0]['children']);
    }
}
