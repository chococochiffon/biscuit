<?php

namespace App\Rules;

use App\Enums\CallContentPlace;
use App\Enums\CallType;
use App\Models\ContentModelRelation;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * call_contentsの1行が、呼び出し方(call_type)ごとに許可された
 * データ種別・表示箇所・表示件数の組み合わせになっているかを検証する。
 * $checkで対象フィールド(content_model_relation_id/place/view_count)を切り替える。
 */
class ValidCallContentCombination implements DataAwareRule, ValidationRule
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    public function __construct(private readonly string $check) {}

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
        // $attributeは "call_contents.{index}.{field}" の形式。
        $index = explode('.', $attribute)[1];
        $row = $this->data['call_contents'][$index] ?? [];
        $callType = CallType::tryFrom((int) ($row['call_type'] ?? 0));

        if (! $callType) {
            return;
        }

        match ($this->check) {
            'content_model_relation_id' => $this->checkContentModelRelation($callType, $value, $fail),
            'place' => $this->checkPlace($callType, $value, $fail),
            'view_count' => $this->checkViewCount($callType, $value, $fail),
            default => null,
        };
    }

    private function checkContentModelRelation(CallType $callType, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            return;
        }

        $relation = ContentModelRelation::find($value);

        if (! $relation) {
            return;
        }

        if (! in_array($relation->content_type, $callType->allowedContentTypes(), true)) {
            $fail(__('選択した呼び出し方ではこのデータ種別は選択できません。'));

            return;
        }

        $requiredModelName = $callType->requiredModelName();

        if ($requiredModelName !== null && $relation->model_name !== $requiredModelName) {
            $fail(__('選択した呼び出し方ではこのモデルは選択できません。'));
        }
    }

    private function checkPlace(CallType $callType, mixed $value, Closure $fail): void
    {
        $place = CallContentPlace::tryFrom((int) $value);

        if ($place && ! in_array($place, $callType->allowedPlaces(), true)) {
            $fail(__('選択した呼び出し方ではこの表示箇所は選択できません。'));
        }
    }

    private function checkViewCount(CallType $callType, mixed $value, Closure $fail): void
    {
        if ($callType->hasFixedViewCount() && (int) $value !== 1) {
            $fail(__('選択した呼び出し方では表示件数は1固定です。'));
        }
    }
}
