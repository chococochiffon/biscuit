<?php

namespace Tests\Feature;

use App\Enums\UserDetailNameSetting;
use App\Models\User;
use App\Models\UserDetail;
use Database\Seeders\UserSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_users_with_details_icons_and_skills(): void
    {
        Storage::fake('public');

        // DatabaseSeeder(WithoutModelEvents)から呼ばれる場合と同じく、モデルイベントを止めて実行する
        Model::withoutEvents(fn () => $this->seed(UserSeeder::class));

        $testUser = User::where('email', 'test@example.com')->firstOrFail();
        $this->assertSame('Test User', $testUser->name);
        $this->assertSame('Test', $testUser->detail->first_name);
        $this->assertSame('User', $testUser->detail->family_name);
        $this->assertSame('Test User', $testUser->detail->nick_name);
        $this->assertSame('2000-01-01', $testUser->detail->birthday->toDateString());
        $this->assertFalse($testUser->detail->view_flag);
        $this->assertSame(UserDetailNameSetting::Hidden, $testUser->detail->name_settings);
        $this->assertStringStartsWith('biscuitの初期設定時に作成されるデフォルトユーザーです。', $testUser->detail->comment);
        $this->assertCount(0, $testUser->detail->skills);

        $chococo = User::where('email', 'chococo.chiffon@gmail.com')->firstOrFail();
        $this->assertSame('chococo_chiffon', $chococo->name);
        $this->assertSame('ちょここ', $chococo->detail->first_name);
        $this->assertSame('しふぉん', $chococo->detail->family_name);
        $this->assertSame('1983-05-04', $chococo->detail->birthday->toDateString());
        $this->assertTrue($chococo->detail->view_flag);
        $this->assertSame(UserDetailNameSetting::NickName, $chococo->detail->name_settings);
        $this->assertSame(['PHP', 'Java', 'JavaScript', 'HTML', 'CSS'], $chococo->detail->skills->pluck('name')->all());
        $this->assertSame([5, 4, 5, 5, 5], $chococo->detail->skills->pluck('level')->all());

        foreach ([$testUser, $chococo] as $user) {
            $path = $user->detail->user_image;
            $this->assertStringStartsWith(UserDetail::USER_IMAGE_DIRECTORY.'/', $path);
            $this->assertSame(
                [UserDetail::USER_IMAGE_SIZE, UserDetail::USER_IMAGE_SIZE],
                array_slice(getimagesizefromstring(Storage::disk('public')->get($path)), 0, 2)
            );
        }
    }

    public function test_users_are_not_duplicated_when_seeding_again(): void
    {
        Storage::fake('public');

        $this->seed(UserSeeder::class);
        $this->seed(UserSeeder::class);

        $this->assertSame(2, User::count());
        $this->assertSame(2, UserDetail::count());
        $this->assertCount(2, Storage::disk('public')->files(UserDetail::USER_IMAGE_DIRECTORY));
    }

    public function test_adds_details_to_existing_user_without_details(): void
    {
        Storage::fake('public');
        // ユーザー詳細の導入前のシーダーで登録された Test User
        $existing = User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com']);

        $this->seed(UserSeeder::class);

        $this->assertSame(1, User::where('email', 'test@example.com')->count());
        $this->assertSame('Test', $existing->fresh()->detail->first_name);
        $this->assertNotNull($existing->fresh()->detail->user_image);
    }

    public function test_keeps_existing_user_details_and_skills(): void
    {
        Storage::fake('public');
        $detail = UserDetail::factory()
            ->for(User::factory()->state(['email' => 'chococo.chiffon@gmail.com']))
            ->create(['nick_name' => '変更済み']);

        $this->seed(UserSeeder::class);

        $this->assertSame(1, $detail->user->detail()->count());
        $this->assertSame('変更済み', $detail->fresh()->nick_name);
        $this->assertCount(0, $detail->fresh()->skills);
    }

    public function test_deletes_icon_files_left_after_database_refresh(): void
    {
        Storage::fake('public');
        $disk = Storage::disk('public');
        // migrate:refresh 後と同じく、レコードはなく前回のファイルだけが残っている状態
        $disk->put(UserDetail::USER_IMAGE_DIRECTORY.'/old.png', 'old');

        $this->seed(UserSeeder::class);

        $disk->assertMissing(UserDetail::USER_IMAGE_DIRECTORY.'/old.png');
        $this->assertEqualsCanonicalizing(
            UserDetail::query()->pluck('user_image')->all(),
            $disk->files(UserDetail::USER_IMAGE_DIRECTORY)
        );
    }
}
