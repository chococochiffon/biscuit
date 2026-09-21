<?php

namespace Tests\Feature;

use App\Enums\UserDetailNameSetting;
use App\Models\Administrator;
use App\Models\User;
use App\Models\UserDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function validUserDetailPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => '太郎',
            'family_name' => '検証',
            'nick_name' => 'たろちゃん',
            'birthday' => '1990-01-01',
            'comment' => 'よろしくお願いします',
            'view_flag' => '1',
            'name_settings' => UserDetailNameSetting::FullName->value,
        ], $overrides);
    }

    public function test_guests_are_redirected_from_user_pages(): void
    {
        $response = $this->get(route('admin.users.index'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_index_displays_users(): void
    {
        $actor = Administrator::factory()->create();
        $user = User::factory()->has(UserDetail::factory(), 'detail')->create(['name' => 'Jane Doe']);

        $response = $this->actingAs($actor, 'admin')->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee($user->name);
    }

    public function test_create_screen_can_be_rendered(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.users.create'));

        $response->assertOk();
    }

    public function test_store_creates_user_with_detail(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->post(route('admin.users.store'), [
            'name' => '検証太郎',
            'email' => 'new-user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_detail' => $this->validUserDetailPayload(),
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'new-user@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('password123', $user->password));

        $detail = $user->detail;
        $this->assertNotNull($detail);
        $this->assertSame('太郎', $detail->first_name);
        $this->assertSame('検証', $detail->family_name);
        $this->assertSame('たろちゃん', $detail->nick_name);
        $this->assertSame('1990-01-01', $detail->birthday->format('Y-m-d'));
        $this->assertTrue($detail->view_flag);
        $this->assertSame(UserDetailNameSetting::FullName, $detail->name_settings);
    }

    public function test_store_uploads_user_image_with_expected_filename(): void
    {
        $this->freezeTime();
        Storage::fake('public');
        $actor = Administrator::factory()->create();
        $file = UploadedFile::fake()->image('photo.jpg');

        $response = $this->actingAs($actor, 'admin')->post(route('admin.users.store'), [
            'name' => '検証太郎',
            'email' => 'new-user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_detail' => $this->validUserDetailPayload(['user_image' => $file]),
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $detail = User::where('email', 'new-user@example.com')->firstOrFail()->detail;
        $expectedPath = 'image/user/'.now()->format('YmdHis').'_user_details_'.$detail->id.'.jpg';
        $this->assertSame($expectedPath, $detail->user_image);
        Storage::disk('public')->assertExists($expectedPath);
    }

    public function test_store_fails_validation_with_missing_fields(): void
    {
        $actor = Administrator::factory()->create();

        $response = $this->actingAs($actor, 'admin')->post(route('admin.users.store'), []);

        $response->assertSessionHasErrors(['name', 'email', 'password', 'user_detail']);
    }

    public function test_store_fails_when_email_already_exists(): void
    {
        $actor = Administrator::factory()->create();
        $existing = User::factory()->create();

        $response = $this->actingAs($actor, 'admin')->post(route('admin.users.store'), [
            'name' => '検証太郎',
            'email' => $existing->email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_detail' => $this->validUserDetailPayload(),
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_show_displays_user(): void
    {
        $actor = Administrator::factory()->create();
        $target = User::factory()->has(UserDetail::factory(), 'detail')->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.users.show', $target));

        $response->assertOk();
        $response->assertSee($target->email);
    }

    public function test_edit_screen_can_be_rendered(): void
    {
        $actor = Administrator::factory()->create();
        $target = User::factory()->has(UserDetail::factory(), 'detail')->create();

        $response = $this->actingAs($actor, 'admin')->get(route('admin.users.edit', $target));

        $response->assertOk();
    }

    public function test_update_modifies_user_and_detail_without_changing_password(): void
    {
        $actor = Administrator::factory()->create();
        $target = User::factory()
            ->has(UserDetail::factory(), 'detail')
            ->create(['password' => Hash::make('original-password')]);

        $response = $this->actingAs($actor, 'admin')->put(route('admin.users.update', $target), [
            'name' => 'Updated Name',
            'email' => $target->email,
            'user_detail' => $this->validUserDetailPayload(['nick_name' => '更新後ニックネーム']),
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $target->refresh();
        $this->assertSame('Updated Name', $target->name);
        $this->assertTrue(Hash::check('original-password', $target->password));
        $this->assertSame('更新後ニックネーム', $target->detail->nick_name);
    }

    public function test_update_changes_password_when_provided(): void
    {
        $actor = Administrator::factory()->create();
        $target = User::factory()
            ->has(UserDetail::factory(), 'detail')
            ->create(['password' => Hash::make('original-password')]);

        $this->actingAs($actor, 'admin')->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
            'user_detail' => $this->validUserDetailPayload(),
        ]);

        $this->assertTrue(Hash::check('new-password', $target->fresh()->password));
    }

    public function test_update_fails_when_email_belongs_to_another_user(): void
    {
        $actor = Administrator::factory()->create();
        $target = User::factory()->has(UserDetail::factory(), 'detail')->create();
        $other = User::factory()->create();

        $response = $this->actingAs($actor, 'admin')->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $other->email,
            'user_detail' => $this->validUserDetailPayload(),
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_destroy_deletes_user(): void
    {
        $actor = Administrator::factory()->create();
        $target = User::factory()->has(UserDetail::factory(), 'detail')->create();

        $response = $this->actingAs($actor, 'admin')->delete(route('admin.users.destroy', $target));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertSoftDeleted('users', ['id' => $target->id]);
    }
}
