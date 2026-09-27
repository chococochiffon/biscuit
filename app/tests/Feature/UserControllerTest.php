<?php

namespace Tests\Feature;

use App\Enums\UserDetailNameSetting;
use App\Models\User;
use App\Models\UserDetail;
use App\Models\UserSkill;
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
        $this->actingAsAdmin();
        $user = User::factory()->has(UserDetail::factory(), 'detail')->create(['name' => 'Jane Doe']);

        $response = $this->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertSee($user->name);
    }

    public function test_create_screen_can_be_rendered(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.users.create'));

        $response->assertOk();
    }

    public function test_store_creates_user_with_detail(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('admin.users.store'), [
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

    public function test_store_creates_skills_in_row_order(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.users.store'), [
            'name' => '検証太郎',
            'email' => 'skill-user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_detail' => $this->validUserDetailPayload([
                'skills' => [
                    ['name' => 'Frontend', 'level' => 74],
                    ['name' => 'Backend API', 'level' => 98],
                ],
            ]),
        ])->assertSessionHasNoErrors();

        $skills = User::where('email', 'skill-user@example.com')->firstOrFail()->detail->skills;
        $this->assertSame(['Frontend', 'Backend API'], $skills->pluck('name')->all());
        $this->assertSame([74, 98], $skills->pluck('level')->all());
        $this->assertSame([0, 1], $skills->pluck('sort_order')->all());
    }

    public function test_store_rejects_skill_level_out_of_range(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('admin.users.store'), [
            'name' => '検証太郎',
            'email' => 'skill-user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_detail' => $this->validUserDetailPayload([
                'skills' => [['name' => '上限超え', 'level' => 101]],
            ]),
        ]);

        $response->assertSessionHasErrors('user_detail.skills.0.level');
        $this->assertDatabaseCount('user_skills', 0);
    }

    public function test_update_syncs_skills_creating_updating_and_deleting_rows(): void
    {
        $this->actingAsAdmin();
        $target = User::factory()->has(UserDetail::factory(), 'detail')->create();
        $kept = UserSkill::factory()->for($target->detail)->create(['name' => '旧スキル', 'level' => 10, 'sort_order' => 0]);
        $removed = UserSkill::factory()->for($target->detail)->create(['sort_order' => 1]);

        $this->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'user_detail' => $this->validUserDetailPayload([
                'skills' => [
                    ['name' => '新スキル', 'level' => 50, 'sort_order' => 0],
                    ['id' => $kept->id, 'name' => '更新スキル', 'level' => 80, 'sort_order' => 1],
                ],
            ]),
        ])->assertSessionHasNoErrors();

        $kept->refresh();
        $this->assertSame('更新スキル', $kept->name);
        $this->assertSame(80, $kept->level);
        $this->assertSame(1, $kept->sort_order);
        $this->assertSoftDeleted($removed);
        $this->assertDatabaseHas('user_skills', ['user_detail_id' => $target->detail->id, 'name' => '新スキル', 'sort_order' => 0]);
    }

    public function test_update_rejects_skill_belonging_to_another_user(): void
    {
        $this->actingAsAdmin();
        $target = User::factory()->has(UserDetail::factory(), 'detail')->create();
        $othersSkill = UserSkill::factory()->create(['name' => '他人のスキル']);

        $response = $this->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'user_detail' => $this->validUserDetailPayload([
                'skills' => [['id' => $othersSkill->id, 'name' => '書き換え', 'level' => 1]],
            ]),
        ]);

        $response->assertSessionHasErrors('user_detail.skills.0.id');
        $this->assertSame('他人のスキル', $othersSkill->fresh()->name);
    }

    public function test_store_rejects_existing_skill_id(): void
    {
        $this->actingAsAdmin();
        $existingSkill = UserSkill::factory()->create(['name' => '既存のスキル']);

        $response = $this->post(route('admin.users.store'), [
            'name' => '検証太郎',
            'email' => 'new-user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_detail' => $this->validUserDetailPayload([
                'skills' => [['id' => $existingSkill->id, 'name' => '書き換え', 'level' => 1]],
            ]),
        ]);

        $response->assertSessionHasErrors('user_detail.skills.0.id');
        $this->assertSame('既存のスキル', $existingSkill->fresh()->name);
    }

    public function test_show_displays_skills(): void
    {
        $this->actingAsAdmin();
        $target = User::factory()->has(UserDetail::factory(), 'detail')->create();
        UserSkill::factory()->for($target->detail)->create(['name' => '表示確認用スキル']);

        $this->get(route('admin.users.show', $target))
            ->assertOk()
            ->assertSee('表示確認用スキル');
    }

    public function test_store_uploads_user_image_with_expected_filename(): void
    {
        $this->freezeTime();
        Storage::fake('public');
        $this->actingAsAdmin();
        $file = UploadedFile::fake()->image('photo.jpg');

        $response = $this->post(route('admin.users.store'), [
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

    public function test_store_resizes_user_image_to_square_icon_from_center_when_crop_is_not_specified(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $this->post(route('admin.users.store'), [
            'name' => '検証太郎',
            'email' => 'new-user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_detail' => $this->validUserDetailPayload(['user_image' => UploadedFile::fake()->image('wide.png', 1600, 900)]),
        ])->assertSessionHasNoErrors();

        $path = User::where('email', 'new-user@example.com')->firstOrFail()->detail->user_image;
        $this->assertSame([250, 250], array_slice(getimagesizefromstring(Storage::disk('public')->get($path)), 0, 2));
    }

    public function test_update_crops_user_image_to_the_selected_area(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $target = User::factory()->has(UserDetail::factory(), 'detail')->create();

        // 左半分が赤、右半分が青の画像を作り、右半分の正方形だけを切り抜く
        $source = imagecreatetruecolor(1000, 500);
        imagefilledrectangle($source, 0, 0, 499, 499, imagecolorallocate($source, 255, 0, 0));
        imagefilledrectangle($source, 500, 0, 999, 499, imagecolorallocate($source, 0, 0, 255));
        $sourcePath = tempnam(sys_get_temp_dir(), 'icon').'.png';
        imagepng($source, $sourcePath);

        $this->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'user_detail' => $this->validUserDetailPayload([
                'user_image' => new UploadedFile($sourcePath, 'split.png', 'image/png', null, true),
                'user_image_crop' => ['x' => 500, 'y' => 0, 'width' => 500, 'height' => 500],
            ]),
        ])->assertSessionHasNoErrors();

        $stored = imagecreatefromstring(Storage::disk('public')->get($target->detail()->firstOrFail()->user_image));
        $this->assertSame([250, 250], [imagesx($stored), imagesy($stored)]);
        $this->assertSame(['red' => 0, 'green' => 0, 'blue' => 255, 'alpha' => 0], imagecolorsforindex($stored, imagecolorat($stored, 10, 10)));
    }

    public function test_store_rejects_invalid_user_image_crop(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('admin.users.store'), [
            'name' => '検証太郎',
            'email' => 'new-user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_detail' => $this->validUserDetailPayload([
                'user_image_crop' => ['x' => -1, 'y' => 'abc', 'width' => 0, 'height' => 100],
            ]),
        ]);

        $response->assertSessionHasErrors([
            'user_detail.user_image_crop.x',
            'user_detail.user_image_crop.y',
            'user_detail.user_image_crop.width',
        ]);
    }

    public function test_create_screen_places_user_detail_in_right_column_with_icon_image_cropper_modal(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.users.create'));

        $response->assertOk()
            ->assertSeeInOrder(['col-lg-6', 'id="password_confirmation"', 'col-lg-6', 'ユーザー詳細', 'id="user_detail_first_name"'], false)
            ->assertSee('data-output-width="250"', false)
            ->assertSee('data-output-height="250"', false)
            ->assertSee('id="image-cropper-modal"', false)
            ->assertSee('data-role="image-dropzone"', false)
            ->assertSee('アイコン画像')
            ->assertDontSee('ユーザー画像');
    }

    public function test_store_fails_validation_with_missing_fields(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('admin.users.store'), []);

        $response->assertSessionHasErrors(['name', 'email', 'password', 'user_detail']);
    }

    public function test_store_fails_when_email_already_exists(): void
    {
        $this->actingAsAdmin();
        $existing = User::factory()->create();

        $response = $this->post(route('admin.users.store'), [
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
        $this->actingAsAdmin();
        $target = User::factory()->has(UserDetail::factory(), 'detail')->create();

        $response = $this->get(route('admin.users.show', $target));

        $response->assertOk();
        $response->assertSee($target->email);
    }

    public function test_edit_screen_can_be_rendered(): void
    {
        $this->actingAsAdmin();
        $target = User::factory()->has(UserDetail::factory(), 'detail')->create();

        $response = $this->get(route('admin.users.edit', $target));

        $response->assertOk();
    }

    public function test_update_modifies_user_and_detail_without_changing_password(): void
    {
        $this->actingAsAdmin();
        $target = User::factory()
            ->has(UserDetail::factory(), 'detail')
            ->create(['password' => Hash::make('original-password')]);

        $response = $this->put(route('admin.users.update', $target), [
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
        $this->actingAsAdmin();
        $target = User::factory()
            ->has(UserDetail::factory(), 'detail')
            ->create(['password' => Hash::make('original-password')]);

        $this->put(route('admin.users.update', $target), [
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
        $this->actingAsAdmin();
        $target = User::factory()->has(UserDetail::factory(), 'detail')->create();
        $other = User::factory()->create();

        $response = $this->put(route('admin.users.update', $target), [
            'name' => $target->name,
            'email' => $other->email,
            'user_detail' => $this->validUserDetailPayload(),
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_destroy_deletes_user(): void
    {
        $this->actingAsAdmin();
        $target = User::factory()->has(UserDetail::factory(), 'detail')->create();

        $response = $this->delete(route('admin.users.destroy', $target));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertSoftDeleted('users', ['id' => $target->id]);
    }
}
