<?php

namespace App\Http\Requests;

use App\Enums\CallType;
use App\Enums\LayoutBlockType;
use App\Enums\LayoutPageType;
use App\Enums\LayoutRegion;
use App\Enums\NavItemLinkType;
use App\Enums\SidebarPosition;
use App\Models\LayoutBlock;
use App\Rules\ValidCallContentCombination;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * レイアウト管理(ページの種類ごとのサイドバーの位置と、領域ごとの部品)の保存。
 * レイアウトは新規登録の画面を持たず、常にこの画面で上書き保存する。
 */
class UpdateLayoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     * 呼び出し方・データ種別・表示件数は呼び出しコンテンツの部品、本文は自由テキストの部品だけ受け付ける(それ以外の部品では捨てる)。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $callContent = 'exclude_unless:blocks.*.block_type,'.LayoutBlockType::CallContent->value;
        $freeText = 'exclude_unless:blocks.*.block_type,'.LayoutBlockType::FreeText->value;
        $navMenu = 'exclude_unless:blocks.*.block_type,'.LayoutBlockType::NavMenu->value;
        $linkType = fn (NavItemLinkType $type) => 'exclude_unless:blocks.*.nav_items.*.link_type,'.$type->value;
        $place = LayoutBlock::CALL_CONTENT_PLACE;

        $sidebarRules = collect(LayoutPageType::cases())
            ->mapWithKeys(fn (LayoutPageType $pageType) => [
                "layouts.{$pageType->value}.sidebar_position" => ['required', new Enum(SidebarPosition::class)],
            ])
            ->all();

        return [
            ...$sidebarRules,

            'blocks' => ['nullable', 'array'],
            'blocks.*.id' => ['nullable', 'integer', Rule::exists('layout_blocks', 'id')->withoutTrashed()],
            'blocks.*.region' => ['required', new Enum(LayoutRegion::class)],
            'blocks.*.block_type' => ['required', new Enum(LayoutBlockType::class)],
            'blocks.*.title' => ['nullable', 'string', 'max:255'],
            'blocks.*.subtitle' => ['nullable', 'string', 'max:255'],
            'blocks.*.call_type' => [$callContent, 'required', new Enum(CallType::class), new ValidCallContentCombination('call_type', $place)],
            'blocks.*.content_model_relation_id' => [$callContent, 'required', 'integer', Rule::exists('content_model_relations', 'id')->withoutTrashed(), new ValidCallContentCombination('content_model_relation_id', $place)],
            'blocks.*.view_count' => [$callContent, 'required', 'integer', 'min:1', 'max:100', new ValidCallContentCombination('view_count', $place)],
            'blocks.*.content' => [$freeText, 'nullable', 'string', 'max:65535'],
            'blocks.*.sort_order' => ['nullable', 'integer', 'min:0'],

            // ナビメニューの項目。リンク先は種類に応じた項目だけ受け付け、URL はサイト内のパス(/・#)・http(s)・mailto だけ許可する
            'blocks.*.nav_items' => [$navMenu, 'nullable', 'array', 'max:'.config('limits.layout_nav_items')],
            'blocks.*.nav_items.*.id' => [$navMenu, 'nullable', 'integer', Rule::exists('layout_nav_items', 'id')->withoutTrashed()],
            'blocks.*.nav_items.*.link_type' => [$navMenu, 'required', new Enum(NavItemLinkType::class)],
            'blocks.*.nav_items.*.label' => [$navMenu, 'nullable', 'required_if:blocks.*.nav_items.*.link_type,'.NavItemLinkType::Url->value, 'string', 'max:255'],
            'blocks.*.nav_items.*.url' => [$navMenu, $linkType(NavItemLinkType::Url), 'required', 'string', 'max:2048', 'regex:#^(/|\#|https?://|mailto:)#i'],
            'blocks.*.nav_items.*.single_page_id' => [$navMenu, $linkType(NavItemLinkType::SinglePage), 'required', 'integer', Rule::exists('single_pages', 'id')->withoutTrashed()],
            'blocks.*.nav_items.*.custom_page_type_id' => [$navMenu, $linkType(NavItemLinkType::CustomPageType), 'required', 'integer', Rule::exists('custom_page_types', 'id')->withoutTrashed()],
            'blocks.*.nav_items.*.sort_order' => [$navMenu, 'nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'blocks.*.nav_items.*.url.regex' => __('URLは「/」から始まるサイト内のパス、または http(s)・mailto のURLを入力してください。'),
            'blocks.*.nav_items.*.label.required_if' => __('リンク先がURLの項目は表示名を入力してください。'),
            'blocks.*.nav_items.max' => __('ナビメニューの項目は:max件まで登録できます。'),
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'layouts.*.sidebar_position' => __('サイドバーの位置'),
            'blocks.*.block_type' => __('部品の種類'),
            'blocks.*.title' => __('見出し'),
            'blocks.*.subtitle' => __('小見出し'),
            'blocks.*.call_type' => __('呼び出し方'),
            'blocks.*.content_model_relation_id' => __('データ種別'),
            'blocks.*.view_count' => __('表示件数'),
            'blocks.*.content' => __('本文'),
            'blocks.*.nav_items' => __('ナビメニューの項目'),
            'blocks.*.nav_items.*.link_type' => __('リンク先の種類'),
            'blocks.*.nav_items.*.label' => __('表示名'),
            'blocks.*.nav_items.*.url' => __('URL'),
            'blocks.*.nav_items.*.single_page_id' => __('固定ページ'),
            'blocks.*.nav_items.*.custom_page_type_id' => __('カスタムページの種類'),
        ];
    }
}
