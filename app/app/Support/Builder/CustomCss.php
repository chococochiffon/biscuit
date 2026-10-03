<?php

namespace App\Support\Builder;

/**
 * ページビルダーの Custom CSS(ページ・コンポーネントの内容の css、テーマの custom_css)と、ブロックの追加のクラス名(ノードの classes)。
 * 書けるのはスーパー管理者だけ(ゲート edit-builder-css)で、ほかの管理者の保存では今の値を保つ(preserve())。
 *
 * 公開側(chococo)は CSS 全体を .page-builder { … } の中にネストして、ビルダーの部分の外へ効かないようにする(エディタは Canvas の中)。
 * そのため保存するときに、ネストから抜け出せる・外部を読み込む・スクリプトを動かせる書き方を断る(errors())。
 * chococo とエディタも同じ確かめをしてから出す。ネストの中では @keyframes・@font-face と body・html への指定は効かない。
 */
final class CustomCss
{
    /**
     * CSS の最大文字数。
     */
    public const MAX_LENGTH = 20000;

    /**
     * ブロックのクラス名の形(英字か _ で始まる、英数字と - _)と、1 つのブロックに付けられる数。
     */
    public const CLASS_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_-]{0,49}\z/';

    public const MAX_CLASSES = 5;

    /**
     * 使えない書き方(コメント・文字列の外で探す正規表現 → 画面に出す名前)。外部を読み込む・スクリプトを動かせるもの。
     *
     * @var array<string, string>
     */
    private const FORBIDDEN = [
        '/@import/i' => '@import',
        '/@charset/i' => '@charset',
        '/@namespace/i' => '@namespace',
        '/expression\s*\(/i' => 'expression()',
        '/javascript\s*:/i' => 'javascript:',
        '/(?<![\w-])behavior\s*:/i' => 'behavior',
        '/-moz-binding/i' => '-moz-binding',
        '/image-set\s*\(/i' => 'image-set()',
        '/(?<![\w-])src\s*\(/i' => 'src()',
    ];

    /**
     * CSS の誤りの一覧(空なら使える)。
     *
     * @return list<string>
     */
    public static function errors(mixed $css): array
    {
        if ($css === null || $css === '') {
            return [];
        }

        if (! is_string($css)) {
            return [__('CSS の形式が正しくありません。')];
        }

        if (mb_strlen($css) > self::MAX_LENGTH) {
            return [__('CSS は :max 文字までで入力してください。', ['max' => self::MAX_LENGTH])];
        }

        // < は </style> で抜け出せ、\ はエスケープで使えない書き方をごまかせるため、どこにも書けない
        if (str_contains($css, '<') || str_contains($css, '\\')) {
            return [__('CSS に「<」と「\\」は使えません。')];
        }

        $code = self::withoutCommentsAndStrings($css);

        if ($code === null) {
            return [__('CSS のコメントか文字列が閉じていません。')];
        }

        $errors = [];

        foreach (self::FORBIDDEN as $pattern => $name) {
            if (preg_match($pattern, $code) === 1) {
                $errors[] = __('CSS に「:value」は使えません。', ['value' => $name]);
            }
        }

        if (! self::hasOnlyLocalUrls($css)) {
            $errors[] = __('CSS の url() には、/storage/… のような / で始まるサイト内のパスだけを書けます。');
        }

        if (! self::hasBalancedBraces($code)) {
            $errors[] = __('CSS の { と } の対応が正しくありません。');
        }

        return $errors;
    }

    /**
     * 保存する CSS(前後の空白を除き、空なら null)。
     */
    public static function normalize(mixed $css): ?string
    {
        $css = is_string($css) ? trim($css) : '';

        return $css === '' ? null : $css;
    }

    /**
     * ブロックのクラス名の誤り(null なら正しい)。
     */
    public static function classesError(mixed $classes): ?string
    {
        if (! is_array($classes) || ! array_is_list($classes) || count($classes) > self::MAX_CLASSES || count(array_unique($classes, SORT_REGULAR)) !== count($classes)) {
            return __('追加のクラス名は、重ならないように :max 個まで指定してください。', ['max' => self::MAX_CLASSES]);
        }

        foreach ($classes as $class) {
            if (! is_string($class) || preg_match(self::CLASS_PATTERN, $class) !== 1) {
                return __('追加のクラス名には、英字か _ で始まる英数字・-・_ だけを使えます。');
            }
        }

        return null;
    }

    /**
     * 内容の Custom CSS とクラス名を、前の内容(今の下書き)の値に戻す(スーパー管理者でない管理者の保存で使う)。
     * クラス名は同じ ID のブロックのものを使い、新しいブロックには付けない。
     *
     * @param  array<string, mixed>  $content
     * @param  array<string, mixed>|null  $previous
     * @return array<string, mixed>
     */
    public static function preserve(array $content, ?array $previous): array
    {
        $classes = [];

        foreach (BuilderContent::nodes($previous ?? []) as $node) {
            if (($node['classes'] ?? []) !== []) {
                $classes[$node['id']] = $node['classes'];
            }
        }

        unset($content['css']);

        if (self::normalize($previous['css'] ?? null) !== null) {
            $content['css'] = $previous['css'];
        }

        $restore = function (array $nodes) use (&$restore, $classes): array {
            return array_map(function (array $node) use ($restore, $classes) {
                unset($node['classes']);

                if (isset($classes[$node['id']])) {
                    $node['classes'] = $classes[$node['id']];
                }

                if (isset($node['children'])) {
                    $node['children'] = $restore($node['children']);
                }

                return $node;
            }, $nodes);
        };

        $content['children'] = $restore($content['children'] ?? []);

        return $content;
    }

    /**
     * コメントと文字列の中身を除いた CSS(閉じていなければ null)。文字列は "" '' だけを残す。
     */
    private static function withoutCommentsAndStrings(string $css): ?string
    {
        $result = '';
        $length = strlen($css);

        for ($i = 0; $i < $length; $i++) {
            $char = $css[$i];

            if ($char === '/' && ($css[$i + 1] ?? '') === '*') {
                $end = strpos($css, '*/', $i + 2);

                if ($end === false) {
                    return null;
                }

                $i = $end + 1;

                continue;
            }

            if ($char === '"' || $char === "'") {
                $end = strpos($css, $char, $i + 1);

                if ($end === false) {
                    return null;
                }

                $result .= $char.$char;
                $i = $end;

                continue;
            }

            $result .= $char;
        }

        return $result;
    }

    /**
     * url() の中身が、すべて / で始まるサイト内のパス(// は不可)か。
     */
    private static function hasOnlyLocalUrls(string $css): bool
    {
        preg_match_all('/url\s*\(\s*(["\']?)([^"\')]*)\1\s*\)/i', $css, $matches);

        foreach ($matches[2] as $url) {
            if (preg_match('#\A/(?!/)[^\s]*\z#', $url) !== 1) {
                return false;
            }
        }

        // url( の数と、確かめた url() の数が合わなければ(形の崩れた url( があれば)使えない
        return preg_match_all('/url\s*\(/i', $css) === count($matches[0]);
    }

    /**
     * { と } が対応しているか(閉じる側が先に来ない・最後に全部閉じている)。
     */
    private static function hasBalancedBraces(string $code): bool
    {
        $depth = 0;

        foreach (str_split($code) as $char) {
            $depth += match ($char) {
                '{' => 1,
                '}' => -1,
                default => 0,
            };

            if ($depth < 0) {
                return false;
            }
        }

        return $depth === 0;
    }
}
