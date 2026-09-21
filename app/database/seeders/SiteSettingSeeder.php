<?php

namespace Database\Seeders;

use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

class SiteSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SiteSetting::query()->firstOrCreate([], [
            'site_title' => 'ビスケット',
            'description' => 'やあ （´・ω・｀)ようこそ、バーボンハウスへ。このテキーラはサービスだから、まず飲んで落ち着いて欲しい。うん、「また」なんだ。済まない。仏の顔もって言うしね、謝って許してもらおうとも思っていない。でも、この項目を見たとき、君は、きっと言葉では言い表せない「ときめき」みたいなものを感じてくれたと思う。殺伐とした世の中で、そういう気持ちを忘れないで欲しいそう思って、この項目を作ったんだ。じゃあ、注文を聞こうか。',
            'site_icon' => SiteSetting::DEFAULT_SITE_ICON_PATH,
            'site_image' => SiteSetting::DEFAULT_SITE_IMAGE_PATH,
        ]);
    }
}
