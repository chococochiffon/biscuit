<?php

namespace App\Enums;

enum CallType: int
{
    case ShortSentence = 1;
    case OriginalText = 2;
    case LinkList = 3;
    case Link = 4;
    case Archive = 5;
    case SkillList = 6;

    /**
     * 表示用のラベルを取得する(現在の言語設定に応じて翻訳される)。
     */
    public function label(): string
    {
        return match ($this) {
            self::ShortSentence => __('短文'),
            self::OriginalText => __('原文'),
            self::LinkList => __('リンクリスト'),
            self::Link => __('リンク'),
            self::Archive => __('アーカイブ'),
            self::SkillList => __('スキルリスト'),
        };
    }

    /**
     * この呼び出し方(call_type)で選択可能なデータ種別(ContentModelRelationのcontent_type)を取得する。
     *
     * @return array<int, CallContentType>
     */
    public function allowedContentTypes(): array
    {
        return match ($this) {
            self::ShortSentence => [CallContentType::SinglePage],
            self::OriginalText => [CallContentType::Article, CallContentType::SinglePage],
            self::LinkList, self::Link => [CallContentType::Article, CallContentType::SinglePage, CallContentType::Custom],
            self::Archive => [CallContentType::Article, CallContentType::Custom],
            self::SkillList => [CallContentType::Custom],
        };
    }

    /**
     * この呼び出し方(call_type)で選択可能な表示箇所(place)を取得する。
     *
     * @return array<int, CallContentPlace>
     */
    public function allowedPlaces(): array
    {
        return match ($this) {
            self::ShortSentence, self::OriginalText, self::SkillList => [CallContentPlace::Top, CallContentPlace::Inside],
            self::LinkList, self::Link, self::Archive => [CallContentPlace::Top, CallContentPlace::Inside, CallContentPlace::Others],
        };
    }

    /**
     * 表示件数(view_count)が1固定(入力不可)かどうか。falseの場合は入力可で、初期値は1をセットする。
     */
    public function hasFixedViewCount(): bool
    {
        return match ($this) {
            self::ShortSentence, self::OriginalText, self::Link => true,
            self::LinkList, self::Archive, self::SkillList => false,
        };
    }

    /**
     * データ種別に加えてモデル名の指定が必須な場合、そのモデル名を取得する(スキルリストはUserDetail固定)。
     */
    public function requiredModelName(): ?string
    {
        return $this === self::SkillList ? 'UserDetail' : null;
    }

    /**
     * フロントエンド(admin.js)へ渡す、call_typeごとの選択肢制御情報をvalueをキーにしたマップで取得する。
     *
     * @return array<int, array{contentTypes: array<int, int>, places: array<int, int>, fixedViewCount: bool, modelName: string|null}>
     */
    public static function jsConstraintsMap(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [
                $case->value => [
                    'contentTypes' => array_map(fn (CallContentType $type) => $type->value, $case->allowedContentTypes()),
                    'places' => array_map(fn (CallContentPlace $place) => $place->value, $case->allowedPlaces()),
                    'fixedViewCount' => $case->hasFixedViewCount(),
                    'modelName' => $case->requiredModelName(),
                ],
            ])
            ->all();
    }
}
