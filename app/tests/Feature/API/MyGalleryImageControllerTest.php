<?php

namespace Tests\Feature\API;

use App\Enums\ArticleApprovalStatus;
use App\Enums\AuditAction;
use App\Http\Controllers\API\AuthController;
use App\Models\AuditLog;
use App\Models\GalleryCategory;
use App\Models\GalleryImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MyGalleryImageControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    private function actingAsUserWithToken(?User $user = null): void
    {
        $this->withToken(($user ?? $this->user)->createToken(AuthController::TOKEN_NAME, expiresAt: now()->addDay())->plainTextToken);
    }

    public function test_guests_cannot_use_my_gallery_image_api(): void
    {
        $this->getJson(route('api.me.gallery-images.index'))->assertUnauthorized();
        $this->postJson(route('api.me.gallery-images.store'))->assertUnauthorized();
    }

    public function test_index_returns_only_own_images_filtered_by_approval(): void
    {
        $draft = GalleryImage::factory()->byUser($this->user)->create();
        $pending = GalleryImage::factory()->byUser($this->user)->pending()->create();
        GalleryImage::factory()->byUser()->create();
        GalleryImage::factory()->create();
        $this->actingAsUserWithToken();

        $response = $this->getJson(route('api.me.gallery-images.index'))->assertOk();
        $this->assertEqualsCanonicalizing([$draft->id, $pending->id], array_column($response->json('data'), 'id'));

        $this->getJson(route('api.me.gallery-images.index', ['approval' => 'pending']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pending->id)
            ->assertJsonPath('data.0.approval', 'pending');
    }

    public function test_store_creates_draft_image_at_the_end_with_audit_log(): void
    {
        Storage::fake('public');
        $category = GalleryCategory::factory()->create(['name' => '風景']);
        GalleryImage::factory()->create(['sort_order' => 5]);
        $this->actingAsUserWithToken();

        $response = $this->post(route('api.me.gallery-images.store'), [
            'image' => UploadedFile::fake()->image('sea.jpg', 2400, 1200),
            'name' => '海',
            'comment' => '夏の海',
            'gallery_category_id' => $category->id,
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.name', '海')
            ->assertJsonPath('data.category.name', '風景')
            ->assertJsonPath('data.approval', 'draft');

        $image = GalleryImage::query()->findOrFail($response->json('data.id'));
        $this->assertSame($this->user->id, $image->user_id);
        $this->assertSame(6, $image->sort_order);
        $this->assertSame([1200, 600], array_slice(getimagesizefromstring(Storage::disk('public')->get($image->image)), 0, 2));
        $this->assertSame(AuditAction::Created, AuditLog::query()->where('subject_id', $image->id)->sole()->action);
    }

    public function test_store_requires_image_and_name(): void
    {
        $this->actingAsUserWithToken();

        $this->postJson(route('api.me.gallery-images.store'), [])
            ->assertJsonValidationErrors(['image', 'name']);
    }

    public function test_other_users_images_are_not_found(): void
    {
        $others = GalleryImage::factory()->byUser()->create();
        $byAdministrator = GalleryImage::factory()->create();
        $this->actingAsUserWithToken();

        $this->getJson(route('api.me.gallery-images.show', $others))->assertNotFound();
        $this->getJson(route('api.me.gallery-images.show', $byAdministrator))->assertNotFound();
        $this->deleteJson(route('api.me.gallery-images.destroy', $others))->assertNotFound();
        $this->assertNotSoftDeleted($others);
    }

    public function test_update_of_published_image_returns_it_to_pending_and_keeps_draft_as_draft(): void
    {
        $published = GalleryImage::factory()->byUser($this->user)->published()->create();
        $draft = GalleryImage::factory()->byUser($this->user)->create();
        $this->actingAsUserWithToken();

        $this->putJson(route('api.me.gallery-images.update', $published), ['name' => '新しい名前'])
            ->assertOk()
            ->assertJsonPath('data.name', '新しい名前')
            ->assertJsonPath('data.approval', 'pending');
        $this->putJson(route('api.me.gallery-images.update', $draft), ['name' => '下書き'])
            ->assertOk()
            ->assertJsonPath('data.approval', 'draft');
    }

    public function test_update_image_stores_new_file_and_returns_published_image_to_pending(): void
    {
        Storage::fake('public');
        $image = GalleryImage::factory()->byUser($this->user)->published()->create(['image' => 'image/gallery/old.jpg']);
        $this->actingAsUserWithToken();

        $this->post(route('api.me.gallery-images.image', $image), ['image' => UploadedFile::fake()->image('new.jpg', 800, 600)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.approval', 'pending');

        $image->refresh();
        $this->assertNotSame('image/gallery/old.jpg', $image->image);
        Storage::disk('public')->assertExists($image->image);
    }

    public function test_submit_and_withdraw_change_approval_with_audit_log(): void
    {
        $image = GalleryImage::factory()->byUser($this->user)->create(['review_comment' => '直してください']);
        $this->actingAsUserWithToken();

        $this->postJson(route('api.me.gallery-images.submit', $image))
            ->assertOk()
            ->assertJsonPath('data.approval', 'pending')
            ->assertJsonPath('data.review_comment', null);

        $this->postJson(route('api.me.gallery-images.withdraw', $image))
            ->assertOk()
            ->assertJsonPath('data.approval', 'draft');

        $this->assertSame(2, AuditLog::query()->where('subject_id', $image->id)->where('action', AuditAction::StatusChanged)->count());
    }

    public function test_submit_and_withdraw_reject_wrong_status(): void
    {
        $published = GalleryImage::factory()->byUser($this->user)->published()->create();
        $draft = GalleryImage::factory()->byUser($this->user)->create();
        $this->actingAsUserWithToken();

        $this->postJson(route('api.me.gallery-images.submit', $published))->assertJsonValidationErrors('approval');
        $this->postJson(route('api.me.gallery-images.withdraw', $draft))->assertJsonValidationErrors('approval');
        $this->assertSame(ArticleApprovalStatus::Published, $published->fresh()->approval);
    }

    public function test_users_allowed_to_skip_approval_publish_on_submit_and_keep_published_images_published(): void
    {
        $user = User::factory()->skipsApproval()->create();
        $draft = GalleryImage::factory()->byUser($user)->create();
        $published = GalleryImage::factory()->byUser($user)->published()->create();
        $this->actingAsUserWithToken($user);

        $this->postJson(route('api.me.gallery-images.submit', $draft))
            ->assertOk()
            ->assertJsonPath('data.approval', 'published');

        $this->putJson(route('api.me.gallery-images.update', $published), ['name' => '変更'])
            ->assertOk()
            ->assertJsonPath('data.approval', 'published');
    }

    public function test_destroy_soft_deletes_own_image_including_published(): void
    {
        $image = GalleryImage::factory()->byUser($this->user)->published()->create();
        $this->actingAsUserWithToken();

        $this->deleteJson(route('api.me.gallery-images.destroy', $image))->assertNoContent();

        $this->assertSoftDeleted($image);
    }
}
