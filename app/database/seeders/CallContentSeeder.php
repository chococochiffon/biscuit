<?php

namespace Database\Seeders;

use App\Enums\CallContentPlace;
use App\Enums\CallType;
use App\Models\CallContent;
use Illuminate\Database\Seeder;

class CallContentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $callContents = [
            [
                'call_name' => 'ArticleLinkList',
                'call_type' => CallType::LinkList,
                'content_model_relation_id' => 1,
                'view_count' => 6,
                'place' => CallContentPlace::Top,
            ],
            [
                'call_name' => 'SinglePage',
                'call_type' => CallType::ShortSentence,
                'content_model_relation_id' => 2,
                'view_count' => 1,
                'place' => CallContentPlace::Top,
            ],
        ];

        foreach ($callContents as $callContent) {
            CallContent::query()->firstOrCreate(
                ['call_name' => $callContent['call_name']],
                $callContent
            );
        }
    }
}
