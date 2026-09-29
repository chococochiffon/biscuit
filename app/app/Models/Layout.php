<?php

namespace App\Models;

use App\Enums\LayoutPageType;
use App\Enums\SidebarPosition;
use Database\Factories\LayoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 公開側のページの種類ごとのレイアウト(サイドバーの位置)。ページの種類ごとに 1 件だけ登録する。
 */
#[Fillable(['page_type', 'sidebar_position'])]
class Layout extends Model
{
    /** @use HasFactory<LayoutFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page_type' => LayoutPageType::class,
            'sidebar_position' => SidebarPosition::class,
        ];
    }

    /**
     * ページの種類ごとのサイドバーの位置を取得する(未登録の種類はサイドバーなし)。
     *
     * @return array<int, SidebarPosition> ページの種類(LayoutPageType の値) → サイドバーの位置
     */
    public static function sidebarPositions(): array
    {
        $layouts = self::query()->get()->keyBy(fn (self $layout) => $layout->page_type->value);

        return collect(LayoutPageType::cases())
            ->mapWithKeys(fn (LayoutPageType $pageType) => [
                $pageType->value => $layouts->get($pageType->value)?->sidebar_position ?? SidebarPosition::None,
            ])
            ->all();
    }
}
