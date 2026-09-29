<?php

namespace App\Rules;

use App\Enums\CallContentPlace;
use App\Enums\CallType;
use App\Models\ContentModelRelation;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * 呼び出しコンテンツの1行(call_contents.*・レイアウトの部品 blocks.* など)が、
 * 表示箇所(place) → 呼び出し方(call_type) → データ種別(モデル名) → 表示件数 の順に
 * 許可された組み合わせになっているかを検証する。
 * $checkで対象フィールド(call_type/content_model_relation_id/view_count)を切り替える。
 * 表示箇所を行で選ばない場合(レイアウトの部品)は $fixedPlace で表示箇所を指定する。
 */
class ValidCallContentCombination implements DataAwareRule, ValidationRule
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    public function __construct(
        private readonly string $check,
        private readonly ?CallContentPlace $fixedPlace = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // $attributeは "call_contents.{index}.{field}" の形式で、最後のフィールド名を除いた部分が行を指す。
        $row = Arr::get($this->data, Str::beforeLast($attribute, '.'), []);
        $callType = CallType::tryFrom((int) ($row['call_type'] ?? 0));

        if (! $callType) {
            return;
        }

        $place = $this->fixedPlace ?? CallContentPlace::tryFrom((int) ($row['place'] ?? 0));

        match ($this->check) {
            'call_type' => $this->checkCallType($callType, $place, $fail),
            'content_model_relation_id' => $this->checkContentModelRelation($callType, $place, $value, $fail),
            'view_count' => $this->checkViewCount($callType, $value, $fail),
            default => null,
        };
    }

    /**
     * 呼び出し方(call_type)は、表示箇所(place)で選択可能なものかどうかを検証する。
     */
    private function checkCallType(CallType $callType, ?CallContentPlace $place, Closure $fail): void
    {
        if (! $place) {
            return;
        }

        if (! in_array($callType, CallType::allowedForPlace($place), true)) {
            $fail(__('選択した表示箇所ではこの呼び出し方は選択できません。'));
        }
    }

    /**
     * データ種別(content_model_relation_id)は、表示箇所(place)・呼び出し方(call_type)との厳密な組み合わせを検証する。
     * 表示箇所が不正な場合は呼び出し方で選択可能なモデル名(全表示箇所の和集合)かどうかのみを検証し、
     * 呼び出し方自体が表示箇所で選択できない場合は呼び出し方側のエラーに任せる。
     */
    private function checkContentModelRelation(CallType $callType, ?CallContentPlace $place, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            return;
        }

        $relation = ContentModelRelation::find($value);

        if (! $relation) {
            return;
        }

        if (! $place) {
            if (! in_array($relation->matrixModelName(), $callType->allowedModelNames(), true)) {
                $fail(__('選択した呼び出し方ではこのデータ種別は選択できません。'));
            }

            return;
        }

        if (! in_array($callType, CallType::allowedForPlace($place), true)) {
            return;
        }

        if (! $callType->supports($relation->matrixModelName(), $place)) {
            $fail(__('選択した表示箇所・呼び出し方ではこのデータ種別は選択できません。'));
        }
    }

    private function checkViewCount(CallType $callType, mixed $value, Closure $fail): void
    {
        if ($callType->hasFixedViewCount() && (int) $value !== 1) {
            $fail(__('選択した呼び出し方では表示件数は1固定です。'));
        }
    }
}
