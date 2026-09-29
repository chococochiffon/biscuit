<?php

namespace App\Http\Resources;

use App\Enums\LayoutBlockType;
use App\Models\CustomPageType;
use App\Support\CallContent\SinglePageContentSource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LayoutBlockResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * すべての部品は block_type(例: nav_menu)・title・subtitle(見出しを持たない部品や未設定は null)を持ち、
     * 部品の種類ごとに次の項目を足す(サイトタイトル・SNSリンク・コピーライトはサイト設定 API の値を使うため足さない)。
     * - nav_menu: ナビに並べる固定ページ single_pages(リンクリスト表示対象を表示順で)とカスタムページの種類 custom_page_types(並び順)
     * - free_text: 本文 content(HTML)
     * - call_content: 呼び出しコンテンツ call_content(呼び出しコンテンツ API の 1 要素と同じ形。データ種別がなくなっている場合は null)
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'block_type' => $this->block_type->apiName(),
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            ...match ($this->block_type) {
                LayoutBlockType::NavMenu => [
                    'single_pages' => SinglePageResource::collection((new SinglePageContentSource)->getLinkList()),
                    'custom_page_types' => CustomPageTypeResource::collection(CustomPageType::query()->ordered()->get()),
                ],
                LayoutBlockType::FreeText => [
                    'content' => $this->content,
                ],
                LayoutBlockType::CallContent => [
                    'call_content' => $this->contentModelRelation ? new CallContentResource($this->toCallContent()) : null,
                ],
                default => [],
            },
        ];
    }
}
