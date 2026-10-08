<?php

namespace App\Support\Builder;

/**
 * 自由配置(v2)のブロックの位置と大きさ(ノードの layout)。自由配置の面(BlockRegistry::isFreeSurface())の直下のブロックだけが持ち、持つのは必須。
 *
 * { "desktop": {x, y, w, h?}, "tablet"?: {x, y, w, h?}, "mobile"?: {x, y, w, h?} }
 * - x・w: 面の中身の幅に対する %(小数 2 桁まで)。x + w は 100 まで
 * - y: 面の上端からの位置(px の整数)
 * - h: 高さ(px の整数)。BlockRegistry の layoutHeight のブロック(画像・ボックス)だけが持てる。ほかのブロックは中身に合わせる
 * - tablet・mobile: その端末だけの位置。なければタブレットはデスクトップの値、スマートフォンは y → x の順に縦 1 列に並べる。
 *   同じ面の兄弟は、端末ごとに「全員が持つか、全員が持たないか」のどちらか(siblingErrors())
 */
final class BuilderLayout
{
    /**
     * 位置を持てる端末(デスクトップは必須)。
     *
     * @var list<string>
     */
    public const DEVICES = ['desktop', 'tablet', 'mobile'];

    /**
     * y・h の最大(px)。
     */
    public const MAX_PIXELS = 20000;

    /**
     * 位置と大きさの誤りの一覧(空なら正しい)。文言には対象のブロックの名前(label)を入れる。
     *
     * @return list<string>
     */
    public static function errors(mixed $layout, string $type, string $label): array
    {
        $replace = ['block' => $label];

        if ($layout === null) {
            return [__('「:block」の位置がありません。', $replace)];
        }

        if (! self::isObject($layout)) {
            return [__('「:block」の位置の形式が正しくありません。', $replace)];
        }

        $errors = [];

        foreach (array_diff(array_keys($layout), self::DEVICES) as $key) {
            $errors[] = __('端末「:device」の設定はありません。', ['device' => $key]);
        }

        if (! array_key_exists('desktop', $layout)) {
            $errors[] = __('「:block」のデスクトップの位置がありません。', $replace);
        }

        foreach (array_intersect(self::DEVICES, array_keys($layout)) as $device) {
            if (! self::isValidBox($layout[$device], BlockRegistry::hasLayoutHeight($type))) {
                $errors[] = __('「:block」の位置(:device)の値が正しくありません。', [...$replace, 'device' => $device]);
            }
        }

        return $errors;
    }

    /**
     * 同じ面の兄弟が、端末ごとに「全員が位置を持つか、全員が持たないか」になっているか。そろっていない端末の一覧を返す。
     *
     * @param  list<mixed>  $siblings
     * @return list<string>
     */
    public static function inconsistentDevices(array $siblings): array
    {
        $nodes = array_filter($siblings, fn (mixed $node) => is_array($node) && self::isObject($node['layout'] ?? null));
        $devices = [];

        foreach (['tablet', 'mobile'] as $device) {
            $count = count(array_filter($nodes, fn (array $node) => array_key_exists($device, $node['layout'])));

            if ($count > 0 && $count < count($siblings)) {
                $devices[] = $device;
            }
        }

        return $devices;
    }

    /**
     * 検証済みの位置を整える(端末を DEVICES の並びに、値を x・y・w・h の並びにし、数値は % を小数 2 桁、px を整数にする)。
     *
     * @param  array<string, array<string, int|float>>  $layout
     * @return array<string, array<string, int|float>>
     */
    public static function normalize(array $layout): array
    {
        $normalized = [];

        foreach (self::DEVICES as $device) {
            if (! isset($layout[$device])) {
                continue;
            }

            $box = $layout[$device];
            $normalized[$device] = [
                'x' => self::percent($box['x']),
                'y' => (int) $box['y'],
                'w' => self::percent($box['w']),
            ];

            if (isset($box['h'])) {
                $normalized[$device]['h'] = (int) $box['h'];
            }
        }

        return $normalized;
    }

    /**
     * 1 つの端末の位置と大きさが正しいか。
     */
    private static function isValidBox(mixed $box, bool $allowsHeight): bool
    {
        if (! self::isObject($box) || array_diff(array_keys($box), ['x', 'y', 'w', 'h']) !== []) {
            return false;
        }

        if (! self::isPercent($box['x'] ?? null, 0) || ! self::isPercent($box['w'] ?? null, 1) || $box['x'] + $box['w'] > 100) {
            return false;
        }

        if (! self::isPixels($box['y'] ?? null, 0)) {
            return false;
        }

        if (array_key_exists('h', $box)) {
            return $allowsHeight && self::isPixels($box['h'], 1);
        }

        return true;
    }

    /**
     * % の値(min〜100、小数 2 桁まで)か。JSON の 50 と 50.0 はどちらも受け付ける。
     */
    private static function isPercent(mixed $value, int $min): bool
    {
        return (is_int($value) || (is_float($value) && is_finite($value)))
            && $value >= $min && $value <= 100
            && abs(round($value, 2) - $value) < 1e-9;
    }

    /**
     * px の値(min〜MAX_PIXELS の整数)か。
     */
    private static function isPixels(mixed $value, int $min): bool
    {
        return is_int($value) && $value >= $min && $value <= self::MAX_PIXELS;
    }

    /**
     * % の値を、整数ならそのまま、小数なら小数 2 桁の数にする。
     */
    private static function percent(int|float $value): int|float
    {
        $rounded = round($value, 2);

        return floor($rounded) === $rounded ? (int) $rounded : $rounded;
    }

    /**
     * JSON のオブジェクトにあたる配列か(空のオブジェクトは PHP では空の配列になるため、空の配列も含める)。
     *
     * @phpstan-assert-if-true array<string, mixed> $value
     */
    private static function isObject(mixed $value): bool
    {
        return is_array($value) && ($value === [] || ! array_is_list($value));
    }
}
