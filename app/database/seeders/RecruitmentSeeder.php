<?php

namespace Database\Seeders;

use App\Models\Recruitment;
use App\Models\Role;
use App\Models\Shop;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RecruitmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Recruitment::factory()->create([
            'shop_id' => Shop::query()->get()->first()->id,
            'role_id' => Role::query()->where('name','=', 'スタッフ')->get()->first()->id,
            'status' => 0,
            'detail' => 'キャストバック80％
ボーナス有
早退・遅出 対応可
性別・種族不問初心者の方大歓迎
研修があるので安心
ノルマ無し',
            'belongings' => '面接はメインアカウントのみ
フレンド枠1枠
リンクシェル2枠
DiscordのVC参加【聞き専用可】
Xアカウント
やる気があり報連相がしっかりできて
楽しく出来る方',
        ]);
        Recruitment::factory()->create([
            'shop_id' => Shop::query()->get()->first()->id,
            'role_id' => Role::query()->where('name','=', 'キャスト')->get()->first()->id,
            'status' => 1,
            'detail' => 'キャストバック80％
ボーナス有
早退・遅出 対応可
性別・種族不問初心者の方大歓迎
研修があるので安心
ノルマ無し',
            'belongings' => '面接はメインアカウントのみ
フレンド枠1枠
リンクシェル2枠
DiscordのVC参加【聞き専用可】
Xアカウント
やる気があり報連相がしっかりできて
楽しく出来る方',
        ]);
    }
}
