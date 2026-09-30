<?php

namespace Tests\Feature\API;

use App\Enums\AuditAction;
use App\Enums\UserDetailNameSetting;
use App\Http\Controllers\API\AuthController;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserDetail;
use App\Models\UserSkill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MeControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private UserDetail $detail;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => '山田', 'email' => 'user@example.com', 'password' => Hash::make('password')]);
        $this->detail = UserDetail::factory()->create(['user_id' => $this->user->id, 'nick_name' => 'やまちゃん']);
    }

    private function actingAsUserWithToken(?User $user = null): string
    {
        $token = ($user ?? $this->user)->createToken(AuthController::TOKEN_NAME, expiresAt: now()->addDay())->plainTextToken;
        $this->withToken($token);

        return $token;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function profileInput(array $overrides = []): array
    {
        return array_replace_recursive([
            'name' => '山田太郎',
            'email' => 'user@example.com',
            'user_detail' => [
                'first_name' => '太郎',
                'family_name' => '山田',
                'nick_name' => 'たろう',
                'birthday' => '2000-01-01',
                'comment' => 'よろしく',
                'view_flag' => true,
                'name_settings' => UserDetailNameSetting::NickName->value,
                'skills' => [['name' => 'PHP', 'level' => 4]],
            ],
        ], $overrides);
    }

    public function test_show_returns_the_logged_in_user_with_detail_and_skills(): void
    {
        UserSkill::factory()->create(['user_detail_id' => $this->detail->id, 'name' => 'Laravel']);
        $this->actingAsUserWithToken();

        $this->getJson(route('api.me.show'))
            ->assertOk()
            ->assertJsonPath('data.name', '山田')
            ->assertJsonPath('data.detail.nick_name', 'やまちゃん')
            ->assertJsonPath('data.detail.skills.0.name', 'Laravel')
            ->assertJsonPath('data.skip_approval', false)
            ->assertJsonMissingPath('data.password');
    }

    public function test_update_profile_saves_detail_and_skills_and_records_audit_log(): void
    {
        $this->actingAsUserWithToken();

        $this->putJson(route('api.me.profile.update'), $this->profileInput())
            ->assertOk()
            ->assertJsonPath('data.name', '山田太郎')
            ->assertJsonPath('data.detail.nick_name', 'たろう')
            ->assertJsonPath('data.detail.skills.0.name', 'PHP');

        $log = AuditLog::query()->sole();
        $this->assertSame([AuditAction::Updated, 'user', $this->user->id], [$log->action, $log->actor_type, $log->actor_id]);
        $this->assertSame(['user', $this->user->id], [$log->subject_type, $log->subject_id]);
        $this->assertSame(['山田', '山田太郎'], $log->changes['name']);
        $this->assertSame(['やまちゃん', 'たろう'], $log->changes['detail.nick_name']);
        $this->assertSame(['created' => 1, 'updated' => 0, 'deleted' => 0], $log->metadata['skills']);
    }

    public function test_update_profile_cannot_change_permission_to_skip_approval(): void
    {
        $this->actingAsUserWithToken();

        $this->putJson(route('api.me.profile.update'), $this->profileInput(['skip_approval' => true]))
            ->assertOk()
            ->assertJsonPath('data.skip_approval', false);

        $this->assertFalse($this->user->fresh()->skip_approval);
    }

    public function test_update_profile_cannot_take_another_users_email_or_skills(): void
    {
        $other = User::factory()->create(['email' => 'other@example.com']);
        $otherSkill = UserSkill::factory()->create(['user_detail_id' => UserDetail::factory()->create(['user_id' => $other->id])->id]);
        $this->actingAsUserWithToken();

        $this->putJson(route('api.me.profile.update'), $this->profileInput([
            'email' => 'other@example.com',
            'user_detail' => ['skills' => [['id' => $otherSkill->id, 'name' => '乗っ取り', 'level' => 1]]],
        ]))->assertUnprocessable()->assertJsonValidationErrors(['email', 'user_detail.skills.0.id']);

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_update_image_saves_cropped_icon(): void
    {
        Storage::fake('public');
        $this->actingAsUserWithToken();

        $this->post(route('api.me.profile.image'), [
            'image' => UploadedFile::fake()->image('icon.png', 400, 300),
            'crop' => ['x' => 50, 'y' => 0, 'width' => 300, 'height' => 300],
        ], ['Accept' => 'application/json'])->assertOk();

        $path = $this->detail->fresh()->user_image;
        Storage::disk('public')->assertExists($path);
        $this->assertSame([UserDetail::USER_IMAGE_SIZE, UserDetail::USER_IMAGE_SIZE], array_slice(getimagesizefromstring(Storage::disk('public')->get($path)), 0, 2));
        $this->assertArrayHasKey('detail.user_image', AuditLog::query()->sole()->changes);
    }

    public function test_update_password_requires_current_password_and_expires_other_tokens(): void
    {
        $otherDeviceToken = $this->user->createToken(AuthController::TOKEN_NAME, expiresAt: now()->addDay())->plainTextToken;
        $token = $this->actingAsUserWithToken();

        $this->putJson(route('api.me.password.update'), [
            'current_password' => 'wrong',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->putJson(route('api.me.password.update'), [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertNoContent();

        $this->assertTrue(Hash::check('new-password-123', $this->user->fresh()->password));
        $log = AuditLog::query()->sole();
        $this->assertTrue($log->metadata['password_changed']);
        $this->assertStringNotContainsString('new-password-123', json_encode($log->toArray()));

        $this->app['auth']->forgetGuards();
        $this->withToken($otherDeviceToken)->getJson(route('api.me.show'))->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson(route('api.me.show'))->assertOk();
    }

    public function test_me_endpoints_require_a_token(): void
    {
        $this->getJson(route('api.me.show'))->assertUnauthorized();
        $this->putJson(route('api.me.profile.update'), $this->profileInput())->assertUnauthorized();
        $this->putJson(route('api.me.password.update'))->assertUnauthorized();
    }
}
