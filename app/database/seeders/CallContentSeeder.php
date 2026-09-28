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
                'call_name' => 'ArticleArchive',
                'title' => '最新記事',
                'call_type' => CallType::Archive,
                'content_model_relation_id' => 1,
                'view_count' => 6,
                'place' => CallContentPlace::Top,
                'sort_order' => 5,
            ],
            [
                'call_name' => 'SinglePage',
                'title' => 'About',
                'subtitle' => 'このサイトについて',
                'call_type' => CallType::ShortSentence,
                'content_model_relation_id' => 2,
                'view_count' => 1,
                'place' => CallContentPlace::Top,
                'sort_order' => 1,
            ],
            // 本文ページ(パス解決API)で、URLから解決した記事・固定ページの本文を入れる枠
            [
                'call_name' => 'ArticleBody',
                'call_type' => CallType::OriginalText,
                'content_model_relation_id' => 1,
                'view_count' => 1,
                'place' => CallContentPlace::Inside,
                'sort_order' => 2,
            ],
            [
                'call_name' => 'SinglePageBody',
                'call_type' => CallType::OriginalText,
                'content_model_relation_id' => 2,
                'view_count' => 1,
                'place' => CallContentPlace::Inside,
                'sort_order' => 3,
            ],
            // トップで、トップへ表示するユーザーのプロフィールとスキルを並べる枠
            [
                'call_name' => 'UserSkill',
                'call_type' => CallType::SkillList,
                'content_model_relation_id' => 3,
                'view_count' => 3,
                'place' => CallContentPlace::Top,
                'sort_order' => 4,
            ],
            // ヘッダーのナビ(その他)に、固定ページへのリンクを並べる枠(ナビでは見出しを使わない)
            [
                'call_name' => 'SinglePageLinkList',
                'call_type' => CallType::LinkList,
                'content_model_relation_id' => 2,
                'view_count' => 10,
                'place' => CallContentPlace::Others,
                'sort_order' => 0,
            ],
            // トップで、ギャラリー画像をタイル状に並べる枠
            [
                'call_name' => 'GalleryTileList',
                'title' => 'Gallery',
                'subtitle' => 'ギャラリー',
                'call_type' => CallType::TileList,
                'content_model_relation_id' => 4,
                'view_count' => 12,
                'place' => CallContentPlace::Top,
                'sort_order' => 6,
            ],
            // トップで、簡易版の Q&A を開閉パネルで並べる枠
            [
                'call_name' => 'QuestionAnswerAccordion',
                'title' => 'Q&A',
                'subtitle' => 'よくある質問',
                'call_type' => CallType::Accordion,
                'content_model_relation_id' => 5,
                'view_count' => 5,
                'place' => CallContentPlace::Top,
                'sort_order' => 7,
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
