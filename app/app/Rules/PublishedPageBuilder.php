<?php

namespace App\Rules;

use App\Models\PageBuilder;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * 公開側にページビルダーの内容を出す切り替え(固定ページの use_builder・サイト設定の top_use_builder)は、
 * そのページビルダーを公開してからでないとオンにできない(公開中の内容がないまま切り替えると、ページの中身が空になるため)。
 */
class PublishedPageBuilder implements ValidationRule
{
    /**
     * @param  PageBuilder|null  $builder  対象のページビルダー(まだなければ null)
     */
    public function __construct(private ?PageBuilder $builder) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (filter_var($value, FILTER_VALIDATE_BOOLEAN) && ! $this->builder?->isPublished()) {
            $fail(__('ページビルダーの内容を公開してから、ページビルダーで表示するようにしてください。'));
        }
    }
}
