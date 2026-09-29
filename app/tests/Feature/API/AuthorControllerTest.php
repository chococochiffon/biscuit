<?php

namespace Tests\Feature\API;

use App\Enums\ArticleApprovalStatus;
use App\Enums\UserDetailNameSetting;
use App\Models\Article;
use App\Models\User;
use App\Models\UserDetail;
use App\Models\UserSkill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthorControllerTest extends TestCase
{
    use RefreshDatabase;

    private function author(array $detail = []): User
    {
        $user = User::factory()->create(['name' => 'アカウント名']);
        UserDetail::factory()->create([
            'user_id' => $user->id,
            'family_name' => '山田',
            'first_name' => '太郎',
            'nick_name' => 'たろう',
            'comment' => 'よろしく',
            'view_flag' => true,
            'name_settings' => UserDetailNameSetting::NickName,
            ...$detail,
        ]);

        return $user;
    }

    public function test_show_returns_public_profile_without_private_fields(): void
    {
        $user = $this->author();
        UserSkill::factory()->create(['user_detail_id' => $user->detail->id, 'name' => 'PHP', 'sort_order' => 1]);
        UserSkill::factory()->create(['user_detail_id' => $user->detail->id, 'name' => 'Vue', 'sort_order' => 0]);

        $response = $this->getJson(route('api.authors.show', $user->id));

        $response->assertOk()
            ->assertJsonPath('data.name', 'たろう')
            ->assertJsonPath('data.profile_path', "/authors/{$user->id}")
            ->assertJsonPath('data.comment', 'よろしく')
            ->assertJsonPath('data.skills.0.name', 'Vue')
            ->assertJsonMissingPath('data.email')
            ->assertJsonMissingPath('data.birthday');
        $this->assertStringNotContainsString('アカウント名', $response->getContent());
    }

    /**
     * @return array<string, array{UserDetailNameSetting, string}>
     */
    public static function nameSettingProvider(): array
    {
        return [
            'フルネーム' => [UserDetailNameSetting::FullName, '山田 太郎'],
            '名前のみ' => [UserDetailNameSetting::FirstNameOnly, '太郎'],
            '非表示なら「投稿者」' => [UserDetailNameSetting::Hidden, '投稿者'],
        ];
    }

    #[DataProvider('nameSettingProvider')]
    public function test_show_follows_name_settings(UserDetailNameSetting $setting, string $expected): void
    {
        $user = $this->author(['name_settings' => $setting]);

        $this->getJson(route('api.authors.show', $user->id))->assertOk()->assertJsonPath('data.name', $expected);
    }

    public function test_show_returns_404_unless_profile_is_public(): void
    {
        $private = $this->author(['view_flag' => false]);
        $deleted = $this->author();
        $deleted->delete();
        $withoutDetail = User::factory()->create();

        $this->getJson(route('api.authors.show', $private->id))->assertNotFound();
        $this->getJson(route('api.authors.show', $deleted->id))->assertNotFound();
        $this->getJson(route('api.authors.show', $withoutDetail->id))->assertNotFound();
        $this->getJson(route('api.authors.show', 999))->assertNotFound();
    }

    public function test_articles_can_be_filtered_by_author_and_include_author(): void
    {
        $user = $this->author();
        $own = Article::factory()->for($user)->create(['approval' => ArticleApprovalStatus::Published]);
        Article::factory()->create(['approval' => ArticleApprovalStatus::Published]);
        Article::factory()->for($user)->create(['approval' => ArticleApprovalStatus::Draft]);

        $this->getJson(route('articles.index', ['author' => $user->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('data.0.author_name', 'たろう')
            ->assertJsonPath('data.0.author.profile_path', "/authors/{$user->id}");
    }

    public function test_article_author_hides_account_name_and_link_for_private_profiles(): void
    {
        $private = $this->author(['view_flag' => false, 'name_settings' => UserDetailNameSetting::Hidden]);
        Article::factory()->for($private)->create(['approval' => ArticleApprovalStatus::Published]);
        Article::factory()->byAdministrator()->create(['approval' => ArticleApprovalStatus::Published]);

        $response = $this->getJson(route('articles.index'))->assertOk();

        $authors = collect($response->json('data'))->pluck('author');
        $this->assertEqualsCanonicalizing(['投稿者', '管理者'], $authors->pluck('name')->all());
        $this->assertSame([null, null], $authors->pluck('profile_path')->all());
        $this->assertStringNotContainsString('アカウント名', $response->getContent());
    }
}
