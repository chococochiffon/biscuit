<?php

namespace Database\Seeders;

use App\Enums\UserDetailNameSetting;
use App\Models\User;
use App\Models\UserDetail;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UserSeeder extends Seeder
{
    /**
     * ユーザー詳細の自己紹介文(初期ユーザー共通)。
     */
    private const COMMENT = <<<'TEXT'
        biscuitの初期設定時に作成されるデフォルトユーザーです。

        サイトの管理やコンテンツの作成・編集など、biscuitの各種管理機能を利用できます。
        セキュリティのため、初回ログイン後にパスワードやプロフィール情報を変更することをおすすめします。
        TEXT;

    /**
     * 初期ユーザーと、そのユーザー詳細・スキルを登録する。
     * アイコン画像は database/seeders/images/user_icon.png を、管理画面からのアップロードと同じく中央を正方形に切り抜いて保存する。
     * 同じメールアドレスのユーザーが登録済みの場合はユーザーを登録せず、ユーザー詳細がなければ詳細・スキルだけを追加する
     * (再実行で重複させない。ユーザー詳細の導入前に登録したユーザーにも詳細を付ける)。
     * 登録の前に、どのユーザー詳細からも参照されていないアイコン画像を削除する(migrate:refresh --seed のたびに古い画像がたまらないようにする)。
     */
    public function run(): void
    {
        $this->deleteUnreferencedImages();

        $this->createUser(
            ['name' => 'Test User', 'email' => 'test@example.com'],
            [
                'first_name' => 'Test',
                'family_name' => 'User',
                'nick_name' => 'Test User',
                'birthday' => '2000-01-01',
                'view_flag' => false,
                'name_settings' => UserDetailNameSetting::Hidden,
            ],
        );

        $this->createUser(
            ['name' => 'chococo_chiffon', 'email' => 'chococo.chiffon@gmail.com'],
            [
                'first_name' => 'ちょここ',
                'family_name' => 'しふぉん',
                'nick_name' => 'ちょここ',
                'birthday' => '1983-05-04',
                'view_flag' => true,
                'name_settings' => UserDetailNameSetting::NickName,
            ],
            [
                ['name' => 'PHP', 'level' => 5, 'sort_order' => 1],
                ['name' => 'Java', 'level' => 4, 'sort_order' => 1],
                ['name' => 'JavaScript', 'level' => 5, 'sort_order' => 2],
                ['name' => 'HTML', 'level' => 5, 'sort_order' => 3],
                ['name' => 'CSS', 'level' => 5, 'sort_order' => 4],
            ],
        );
    }

    /**
     * ユーザーとユーザー詳細(アイコン画像を含む)・スキルをまとめて登録する(途中で失敗したら全体を取り消す)。
     * ユーザーが登録済みなら詳細・スキルだけを追加し、詳細も登録済みなら何もしない。
     *
     * @param  array{name: string, email: string}  $user
     * @param  array<string, mixed>  $detail
     * @param  list<array{name: string, level: int, sort_order: int}>  $skills
     */
    private function createUser(array $user, array $detail, array $skills = []): void
    {
        DB::transaction(function () use ($user, $detail, $skills) {
            $model = User::query()->where('email', $user['email'])->first() ?? User::factory()->create($user);

            if ($model->detail()->exists()) {
                return;
            }

            $userDetail = $model->detail()->create($detail + ['comment' => self::COMMENT]);

            $filename = 'user_icon.png';
            $userDetail->update([
                'user_image' => $userDetail->storeUserImage(new UploadedFile(DefaultImageSeeder::sourcePath($filename), $filename, null, null, true)),
            ]);

            $userDetail->skills()->createMany($skills);
        });
    }

    /**
     * 保存先ディレクトリのアイコン画像のうち、ユーザー詳細のレコード(論理削除済みを含む)から参照されていないものを削除する。
     */
    private function deleteUnreferencedImages(): void
    {
        $disk = Storage::disk('public');
        $referencedPaths = UserDetail::withTrashed()->pluck('user_image')->filter()->all();

        $disk->delete(array_values(array_diff($disk->files(UserDetail::USER_IMAGE_DIRECTORY), $referencedPaths)));
    }
}
