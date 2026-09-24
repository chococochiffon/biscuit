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
     * 指定した表示箇所(place)で選択可能な呼び出し方(call_type)を取得する(全モデルの和集合)。
     *
     * @return array<int, self>
     */
    public static function allowedForPlace(CallContentPlace $place): array
    {
        $callTypes = collect(self::combinationMatrix()[$place->value] ?? [])->flatten()->all();

        return array_values(array_filter(self::cases(), fn (self $case) => in_array($case, $callTypes, true)));
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
     * フロントエンド(admin.js)へ渡す選択肢制御情報を取得する。
     * 入力は 表示箇所(place) → 呼び出し方(call_type) → データ種別(モデル名) → 表示件数 の順に絞り込む。
     * - places: 表示箇所ごとに選択可能な呼び出し方と、呼び出し方ごとに選択可能なモデル名
     * - modelNamesByCallType: 表示箇所が未選択の場合に使う、呼び出し方ごとのモデル名(全表示箇所の和集合)
     * - fixedViewCount: 呼び出し方ごとの表示件数1固定可否
     *
     * @return array{places: array<int, array{callTypes: array<int, int>, modelNamesByCallType: array<int, array<int, string>>}>, modelNamesByCallType: array<int, array<int, string>>, fixedViewCount: array<int, bool>}
     */
    public static function jsConstraintsMap(): array
    {
        return [
            'places' => collect(CallContentPlace::cases())
                ->mapWithKeys(fn (CallContentPlace $place) => [
                    $place->value => [
                        'callTypes' => array_map(fn (self $case) => $case->value, self::allowedForPlace($place)),
                        'modelNamesByCallType' => collect(self::allowedForPlace($place))
                            ->mapWithKeys(fn (self $case) => [$case->value => $case->allowedModelNames($place)])
                            ->all(),
                    ],
                ])
                ->all(),
            'modelNamesByCallType' => collect(self::cases())
                ->mapWithKeys(fn (self $case) => [$case->value => $case->allowedModelNames()])
                ->all(),
            'fixedViewCount' => collect(self::cases())
                ->mapWithKeys(fn (self $case) => [$case->value => $case->hasFixedViewCount()])
                ->all(),
        ];
    }
}
