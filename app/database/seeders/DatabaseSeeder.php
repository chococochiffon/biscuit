<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(AdministratorSeeder::class);
        $this->call(DefaultImageSeeder::class);
        $this->call(UserSeeder::class);
        $this->call(SiteSettingSeeder::class);
        $this->call(TopSliderImageSeeder::class);
        $this->call(SinglePageSeeder::class);
        $this->call(ArticleSeeder::class);
        $this->call(GallerySeeder::class);
        $this->call(ContentModelRelationSeeder::class);
        $this->call(CallContentSeeder::class);
        $this->call(LayoutSeeder::class);
        $this->call(QuestionAnswerSeeder::class);
    }
}
