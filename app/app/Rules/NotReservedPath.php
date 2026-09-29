<?php

namespace App\Rules;

use App\Models\CustomPageType;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * 記事・固定ページの URL の先頭(親パスの最初の階層。親パスがなければスラッグ)が、
 * カスタムページの URL の先頭(例: /recipes。論理削除済みの種類を含む)や、投稿者ページの先頭(/authors)と重ならないか検証する。
 */
class NotReservedPath implements DataAwareRule, ValidationRule
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
     * 親パスは最初の階層を、スラッグは親パスがないときだけ、カスタムページ・投稿者ページの URL の先頭と比べる。
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($attribute === 'slug' && filled($this->data['parent_path'] ?? null)) {
            return;
        }

        $firstSegment = explode('/', trim((string) $value, '/'))[0];

        if ($firstSegment === User::AUTHOR_PATH_PREFIX) {
            $fail(__('URL の先頭「/:prefix」は投稿者ページで使われています。', ['prefix' => User::AUTHOR_PATH_PREFIX]));

            return;
        }

        $type = CustomPageType::withTrashed()->get()->first(fn (CustomPageType $type) => $type->pluralName() === $firstSegment);

        if ($type !== null) {
            $fail(__('URL の先頭「:path」はカスタムページ「:label」で使われています。', ['path' => $type->publicPath(), 'label' => $type->label]));
        }
    }
}
