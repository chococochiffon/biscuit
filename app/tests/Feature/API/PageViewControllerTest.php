<?php

namespace Tests\Feature\API;

use App\Enums\ArticleApprovalStatus;
use App\Enums\CustomPageBaseType;
use App\Http\Controllers\API\AuthController;
use App\Http\Requests\API\RecordPageViewRequest;
use App\Models\Article;
use App\Models\CustomPages\CustomPageEntry;
use App\Models\CustomPageType;
use App\Models\PageView;
use App\Models\SinglePage;
use App\Models\User;
use App\Support\CustomPages\CustomPageSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * PV の記録(chococo のサーバーが中継する POST /api/page-views)。
 */
class PageViewControllerTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'test-page-view-key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['page_views.forward_key' => self::KEY]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function recordPageView(array $data, ?string $key = self::KEY): TestResponse
    {
        return $this->withHeader(RecordPageViewRequest::KEY_HEADER, (string) $key)
            ->postJson(route('api.page-views.store'), $data);
    }

    public function test_viewing_a_published_article_records_one_page_view_with_its_content(): void
    {
        $article = Article::factory()->published()->create(['parent_path' => 'news', 'slug' => 'first-post']);

        $this->recordPageView(['path' => '/news/first-post'])->assertCreated();

        $this->assertSame(1, PageView::query()->count());
        $pageView = PageView::query()->sole();
        $this->assertSame('article', $pageView->content_type);
        $this->assertSame($article->id, $pageView->content_id);
        $this->assertSame('/news/first-post', $pageView->path);
        $this->assertNotNull($pageView->viewed_at);
    }

    public function test_content_id_and_type_are_saved_for_each_page(): void
    {
        $first = Article::factory()->published()->create(['parent_path' => 'news', 'slug' => 'first']);
        $second = Article::factory()->published()->create(['parent_path' => 'news', 'slug' => 'second']);
        $singlePage = SinglePage::factory()->create(['parent_path' => 'company', 'slug' => 'about']);

        $this->recordPageView(['path' => '/news/second'])->assertCreated();
        $this->recordPageView(['path' => 'news/first/'])->assertCreated();
        $this->recordPageView(['path' => '/company/about'])->assertCreated();
        $this->recordPageView(['path' => '/'])->assertCreated();

        $this->assertSame([
            ['article', $second->id, '/news/second'],
            ['article', $first->id, '/news/first'],
            ['single_page', $singlePage->id, '/company/about'],
            ['top', null, '/'],
        ], PageView::query()->orderBy('id')->get()->map(fn (PageView $pageView) => [$pageView->content_type, $pageView->content_id, $pageView->path])->all());
    }

    public function test_custom_pages_and_their_list_are_recorded_with_the_type_name(): void
    {
        $type = CustomPageType::factory()->create(['name' => 'recipe', 'label' => 'レシピ', 'base_type' => CustomPageBaseType::Article]);
        (new CustomPageSchema)->create($type);
        $entry = CustomPageEntry::queryFor($type)->create([
            'title' => '肉じゃが',
            'content' => '<p>本文</p>',
            'slug' => 'nikujaga',
            'approval' => ArticleApprovalStatus::Published,
            'publication_start_datetime' => now()->subDay(),
        ]);

        $this->recordPageView(['path' => '/recipes/nikujaga'])->assertCreated();
        $this->recordPageView(['path' => '/recipes'])->assertCreated();

        $this->assertSame([
            ['custom_page:recipe', $entry->id],
            ['custom_page_list:recipe', null],
        ], PageView::query()->orderBy('id')->get()->map(fn (PageView $pageView) => [$pageView->content_type, $pageView->content_id])->all());
    }

    public function test_pages_that_would_be_404_are_not_recorded(): void
    {
        Article::factory()->create(['parent_path' => 'news', 'slug' => 'draft-post', 'approval' => ArticleApprovalStatus::Draft]);
        SinglePage::factory()->create(['slug' => 'deleted'])->delete();

        $this->recordPageView(['path' => '/unknown'])->assertNotFound();
        $this->recordPageView(['path' => '/news/draft-post'])->assertNotFound();
        $this->recordPageView(['path' => '/deleted'])->assertNotFound();

        $this->assertSame(0, PageView::query()->count());
    }

    public function test_requests_without_the_shared_key_are_rejected(): void
    {
        $this->recordPageView(['path' => '/'], key: null)->assertForbidden();
        $this->recordPageView(['path' => '/'], key: 'wrong-key')->assertForbidden();

        // 鍵が未設定なら、どの鍵でも受け付けない
        config(['page_views.forward_key' => null]);
        $this->recordPageView(['path' => '/'], key: '')->assertForbidden();

        $this->assertSame(0, PageView::query()->count());
    }

    public function test_path_is_required_and_ip_must_be_valid(): void
    {
        $this->recordPageView([])->assertUnprocessable()->assertJsonValidationErrors('path');
        $this->recordPageView(['path' => '/', 'ip' => 'not-an-ip'])->assertUnprocessable()->assertJsonValidationErrors('ip');
    }

    public function test_a_visitor_id_is_issued_on_the_first_view_and_reused_afterwards(): void
    {
        $visitorId = $this->recordPageView(['path' => '/'])->assertCreated()->json('visitor_id');

        $this->assertTrue(Str::isUuid($visitorId));
        $this->assertSame($visitorId, PageView::query()->sole()->visitor_id);

        $this->recordPageView(['path' => '/', 'visitor_id' => $visitorId])
            ->assertCreated()
            ->assertJsonPath('visitor_id', $visitorId);

        $this->assertSame(2, PageView::query()->where('visitor_id', $visitorId)->count());
    }

    public function test_an_invalid_visitor_id_is_replaced_with_a_new_one(): void
    {
        $visitorId = $this->recordPageView(['path' => '/', 'visitor_id' => 'tampered'])->assertCreated()->json('visitor_id');

        $this->assertNotSame('tampered', $visitorId);
        $this->assertTrue(Str::isUuid($visitorId));
    }

    public function test_user_id_is_saved_when_a_logged_in_user_token_is_sent(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken(AuthController::TOKEN_NAME, expiresAt: now()->addDay())->plainTextToken;

        $this->withToken($token)->recordPageView(['path' => '/'])->assertCreated();
        // 同じテストの中では認証済みのユーザーが残るため、リクエストごとに忘れさせる
        $this->app['auth']->forgetGuards();
        $this->withToken('invalid-token')->recordPageView(['path' => '/'])->assertCreated();

        $this->assertSame([$user->id, null], PageView::query()->orderBy('id')->pluck('user_id')->all());
    }

    public function test_ip_address_and_session_id_are_saved_only_as_hashes(): void
    {
        $this->recordPageView([
            'path' => '/',
            'ip' => '203.0.113.5',
            'session_id' => 'session-abc',
            'user_agent' => 'Mozilla/5.0 (Test)',
            'referer' => 'https://www.google.com/',
        ])->assertCreated();

        $pageView = PageView::query()->sole();
        $this->assertSame(hash_hmac('sha256', '203.0.113.5', (string) config('app.key')), $pageView->ip_hash);
        $this->assertSame(hash_hmac('sha256', 'session-abc', (string) config('app.key')), $pageView->session_id);
        $this->assertSame('Mozilla/5.0 (Test)', $pageView->user_agent);
        $this->assertSame('https://www.google.com/', $pageView->referer);

        // IP アドレス・セッションの識別子そのものは、どの列にも残らない
        $raw = json_encode(PageView::query()->toBase()->first());
        $this->assertStringNotContainsString('203.0.113.5', $raw);
        $this->assertStringNotContainsString('session-abc', $raw);
    }

    public function test_ipv6_addresses_are_hashed_in_the_same_notation(): void
    {
        $this->recordPageView(['path' => '/', 'ip' => '2001:0DB8:0000:0000:0000:0000:0000:0001'])->assertCreated();
        $this->recordPageView(['path' => '/', 'ip' => '2001:db8::1'])->assertCreated();

        $hashes = PageView::query()->pluck('ip_hash');
        $this->assertSame(hash_hmac('sha256', '2001:db8::1', (string) config('app.key')), $hashes[0]);
        $this->assertSame($hashes[0], $hashes[1]);
    }

    public function test_excluded_user_agents_are_not_recorded(): void
    {
        config(['page_views.excluded_user_agents' => ['/googlebot/i']]);

        $this->recordPageView(['path' => '/', 'user_agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'])->assertCreated();
        $this->recordPageView(['path' => '/', 'user_agent' => 'Mozilla/5.0 (Windows NT 10.0)'])->assertCreated();

        $this->assertSame(['Mozilla/5.0 (Windows NT 10.0)'], PageView::query()->pluck('user_agent')->all());
    }

    public function test_reloads_are_counted_unless_a_duplicate_window_is_configured(): void
    {
        $visitorId = (string) Str::uuid();

        $this->recordPageView(['path' => '/', 'visitor_id' => $visitorId]);
        $this->recordPageView(['path' => '/', 'visitor_id' => $visitorId]);
        $this->assertSame(2, PageView::query()->count());

        config(['page_views.duplicate_window_seconds' => 60]);
        $this->recordPageView(['path' => '/', 'visitor_id' => $visitorId])->assertCreated();
        $this->assertSame(2, PageView::query()->count());

        $this->travel(61)->seconds();
        $this->recordPageView(['path' => '/', 'visitor_id' => $visitorId])->assertCreated();
        $this->assertSame(3, PageView::query()->count());
    }
}
