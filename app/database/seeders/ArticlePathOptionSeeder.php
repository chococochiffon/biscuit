<?php

namespace Database\Seeders;

use App\Models\ArticlePathOption;
use Illuminate\Database\Seeder;

class ArticlePathOptionSeeder extends Seeder
{
    /**
     * ユーザーが記事を投稿するときに選ぶ投稿先の初期値(記事のサンプルと同じ階層)を登録する。登録済みなら登録しない。
     */
    public function run(): void
    {
        if (ArticlePathOption::query()->exists()) {
            return;
        }

        foreach ([['ブログ', 'blog'], ['ブログ(暮らし)', 'blog/life']] as $sortOrder => [$label, $parentPath]) {
            ArticlePathOption::create(['label' => $label, 'parent_path' => $parentPath, 'sort_order' => $sortOrder]);
        }
    }
}
