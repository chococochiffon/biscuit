<?php

namespace App\Http\Requests\Concerns;

use App\Models\SinglePage;
use App\Rules\AvailablePath;
use Illuminate\Database\Eloquent\Model;

/**
 * 公開側URLの親パス(parent_path)・スラッグ(slug)を入力する記事・固定ページのフォームリクエスト用の共通処理。
 */
trait ValidatesPath
{
    /**
     * 親パス・スラッグの前後の空白とスラッシュを取り除く(空になった場合は未入力扱い)。
     */
    protected function preparePathInput(): void
    {
        $this->merge([
            'parent_path' => trim((string) $this->input('parent_path'), ' /') ?: null,
            'slug' => trim((string) $this->input('slug'), ' /') ?: null,
        ]);
    }

    /**
     * 親パス・スラッグのバリデーションルール。数字だけのスラッグは記事番号用に予約しているため使えない。
     *
     * @param  Model|null  $ignore  更新時の自分自身(URLの重複判定から除外する)
     * @return array<string, array<mixed>>
     */
    protected function pathRules(bool $slugRequired, ?Model $ignore = null): array
    {
        return [
            'parent_path' => ['nullable', 'string', 'max:255', 'regex:#^'.SinglePage::SLUG_PATTERN.'(?:/'.SinglePage::SLUG_PATTERN.')*$#'],
            'slug' => [
                $slugRequired ? 'required' : 'nullable',
                'string', 'max:255',
                'regex:#^'.SinglePage::SLUG_PATTERN.'$#',
                'not_regex:#^[0-9]+$#',
                new AvailablePath($ignore),
            ],
        ];
    }

    /**
     * 親パス・スラッグの書式エラーのメッセージ(使える文字を案内する)。
     *
     * @return array<string, string>
     */
    protected function pathMessages(): array
    {
        return [
            'parent_path.regex' => __('親パスは半角英小文字・数字・ハイフンで入力し、階層は「/」で区切ってください。'),
            'slug.regex' => __('スラッグは半角英小文字・数字・ハイフンで入力してください。'),
            'slug.not_regex' => __('数字だけのスラッグは記事番号用のため使えません。'),
        ];
    }
}
