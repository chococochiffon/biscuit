<?php

namespace App\Rules;

use App\Models\CustomPageType;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * 記事・固定ページの URL の先頭(親パスの最初の階層。親パスがなければスラッグ)が、
 * カスタムページの URL の先頭(例: /recipes。論理削除済みの種類を含む)と重ならないか検証する。
 */
class NotReservedByCustomPage implements DataAwareRule, ValidationRule
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * 親パスは最初の階層を、スラッグは親パスがないときだけ、カスタムページの URL の先頭と比べる。
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($attribute === 'slug' && filled($this->data['parent_path'] ?? null)) {
            return;
        }

        $firstSegment = explode('/', trim((string) $value, '/'))[0];
        $type = CustomPageType::withTrashed()->get()->first(fn (CustomPageType $type) => $type->pluralName() === $firstSegment);

        if ($type !== null) {
            $fail(__('URL の先頭「:path」はカスタムページ「:label」で使われています。', ['path' => $type->publicPath(), 'label' => $type->label]));
        }
    }
}
