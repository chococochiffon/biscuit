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
 * 公開側のページの種類ごとのレイアウト(サイドバーの位置・パンくずを表示するか)。ページの種類ごとに 1 件だけ登録する。
 */
#[Fillable(['page_type', 'sidebar_position', 'show_breadcrumbs'])]
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
            'show_breadcrumbs' => 'boolean',
        ];
    }

    /**
     * すべてのページの種類のレイアウトを取得する。
     * 未登録の種類は初期値(サイドバーなし、パンくずはトップ以外で表示)の保存していないレイアウトにする。
     *
     * @return array<int, self> ページの種類(LayoutPageType の値) → レイアウト
     */
    public static function forPageTypes(): array
    {
        $layouts = self::query()->get()->keyBy(fn (self $layout) => $layout->page_type->value);

        return collect(LayoutPageType::cases())
            ->mapWithKeys(fn (LayoutPageType $pageType) => [
                $pageType->value => $layouts->get($pageType->value) ?? new self([
                    'page_type' => $pageType,
                    'sidebar_position' => SidebarPosition::None,
                    'show_breadcrumbs' => $pageType !== LayoutPageType::Top,
                ]),
            ])
            ->all();
    }
}
