<?php

namespace Database\Seeders;

use App\Enums\CallContentType;
use App\Models\ContentModelRelation;
use Illuminate\Database\Seeder;

class ContentModelRelationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $relations = [
            ['content_type' => CallContentType::Article, 'model_name' => 'Article', 'table_name' => 'articles'],
            ['content_type' => CallContentType::SinglePage, 'model_name' => 'SinglePage', 'table_name' => 'single_pages'],
            ['content_type' => CallContentType::LinkList, 'model_name' => 'UserDetail', 'table_name' => 'user_details'],
        ];

        foreach ($relations as $relation) {
            ContentModelRelation::query()->firstOrCreate(
                ['content_type' => $relation['content_type'], 'model_name' => $relation['model_name']],
                ['table_name' => $relation['table_name']]
            );
        }
    }
}
