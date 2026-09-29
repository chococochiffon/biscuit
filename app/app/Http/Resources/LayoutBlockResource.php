<?php

namespace App\Http\Resources;

use App\Enums\LayoutBlockType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LayoutBlockResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * すべての部品は block_type(例: nav_menu)・title・subtitle(見出しを持たない部品や未設定は null)を持ち、
     * 部品の種類ごとに次の項目を足す(サイトタイトル・SNSリンク・コピーライトはサイト設定 API の値を使うため足さない)。
     * - nav_menu: ナビに並べる項目 items(各項目は label・path・prefix(下の階層のページでも選択中にするか)。項目が未登録なら自動で並べる。LayoutBlock::navMenuItems())
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
                    'items' => $this->navMenuItems(),
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
