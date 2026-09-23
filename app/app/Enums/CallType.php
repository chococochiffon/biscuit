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
     * 表示箇所(place)・モデル名(ContentModelRelationのmodel_name)ごとに、
     * 選択可能な呼び出し方(call_type)を定義したマトリクス。
     *
     * @return array<int, array<string, array<int, self>>>
     */
    private static function combinationMatrix(): array
    {
        return [
            CallContentPlace::Top->value => [
                'Article' => [self::Link, self::Archive],
                'SinglePage' => [self::ShortSentence, self::LinkList, self::Link],
                'UserDetail' => [self::LinkList, self::SkillList],
            ],
            CallContentPlace::Inside->value => [
                'Article' => [self::OriginalText],
                'SinglePage' => [self::OriginalText],
                'UserDetail' => [self::LinkList, self::SkillList],
            ],
            CallContentPlace::Others->value => [
                'Article' => [self::LinkList, self::Link, self::Archive],
                'SinglePage' => [self::LinkList],
                'UserDetail' => [self::LinkList],
            ],
        ];
    }

    /**
     * このcall_typeが、指定したモデル名(model_name)・表示箇所(place)の組み合わせで選択可能かどうか。
     */
    public function supports(string $modelName, CallContentPlace $place): bool
    {
        $callTypes = self::combinationMatrix()[$place->value][$modelName] ?? [];

        return in_array($this, $callTypes, true);
    }

    /**
     * この呼び出し方(call_type)で選択可能なモデル名(ContentModelRelationのmodel_name)を取得する。
     * $placeを指定した場合はその表示箇所限定、未指定の場合は全表示箇所の和集合を返す。
     *
     * @return array<int, string>
     */
    public function allowedModelNames(?CallContentPlace $place = null): array
    {
        $places = $place ? [$place] : CallContentPlace::cases();

        return collect($places)
            ->flatMap(fn (CallContentPlace $p) => collect(self::combinationMatrix()[$p->value] ?? [])
                ->filter(fn (array $callTypes) => in_array($this, $callTypes, true))
                ->keys())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * この呼び出し方(call_type)で選択可能な表示箇所(place)を取得する。
     * $modelNameを指定した場合はそのモデル限定、未指定の場合は全モデルの和集合を返す。
     *
     * @return array<int, CallContentPlace>
     */
    public function allowedPlaces(?string $modelName = null): array
    {
        return collect(self::combinationMatrix())
            ->filter(function (array $byModelName) use ($modelName) {
                $callTypes = $modelName ? ($byModelName[$modelName] ?? []) : collect($byModelName)->flatten()->all();

                return in_array($this, $callTypes, true);
            })
            ->keys()
            ->map(fn (int $value) => CallContentPlace::from($value))
            ->all();
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
     * フロントエンド(admin.js)へ渡す、call_typeごとの選択肢制御情報をvalueをキーにしたマップで取得する。
     *
     * @return array<int, array{modelNames: array<int, string>, places: array<int, int>, placesByModelName: array<string, array<int, int>>, fixedViewCount: bool}>
     */
    public static function jsConstraintsMap(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [
                $case->value => [
                    'modelNames' => $case->allowedModelNames(),
                    'places' => array_map(fn (CallContentPlace $place) => $place->value, $case->allowedPlaces()),
                    'placesByModelName' => collect($case->allowedModelNames())
                        ->mapWithKeys(fn (string $modelName) => [
                            $modelName => array_map(fn (CallContentPlace $place) => $place->value, $case->allowedPlaces($modelName)),
                        ])
                        ->all(),
                    'fixedViewCount' => $case->hasFixedViewCount(),
                ],
            ])
            ->all();
    }
}
