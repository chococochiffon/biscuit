<?php

namespace App\Models;

use App\Support\Builder\ThemeRegistry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ページビルダーのテーマ(ビルダーのブロックにだけ効く色とフォント)。サイトに 1 つだけ登録し、取得は current() を使う。
 * 項目の定義は Support\Builder\ThemeRegistry。
 */
#[Fillable(['colors', 'heading_font', 'body_font'])]
class PageBuilderTheme extends Model
{
    use SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'colors' => 'array',
        ];
    }

    /**
     * 今のテーマ(未登録なら既定の色で、保存していないテーマ)。
     */
    public static function current(): self
    {
        return self::query()->orderBy('id')->first() ?? new self(['colors' => ThemeRegistry::defaultColors()]);
    }

    /**
     * テーマの色(登録していない色は既定値で補う)。
     *
     * @return array<string, string>
     */
    public function colorValues(): array
    {
        return array_merge(ThemeRegistry::defaultColors(), array_intersect_key($this->colors ?? [], ThemeRegistry::COLORS));
    }

    /**
     * エディタ・公開側(chococo)に渡す形。
     *
     * @return array{colors: array<string, string>, fonts: array{heading: array{key: string, family: string, href: string}|null, body: array{key: string, family: string, href: string}|null}}
     */
    public function toPresentation(): array
    {
        return [
            'colors' => $this->colorValues(),
            'fonts' => [
                'heading' => ThemeRegistry::font($this->heading_font),
                'body' => ThemeRegistry::font($this->body_font),
            ],
        ];
    }
}
