<?php

namespace App\Support\Builder;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

/**
 * ページビルダーのブロックの表示条件(ノードの visibility。書かなければ常に表示)。
 *
 * { "hideOn"?: ["desktop" | "tablet" | "mobile", ...], "startAt"?: "YYYY-MM-DDTHH:MM", "endAt"?: "YYYY-MM-DDTHH:MM" }
 * - hideOn: 表示しない端末。公開側(chococo)が端末の画面幅のメディアクエリで隠す(HTML には残る)。すべての端末は選べない。
 * - startAt/endAt: 表示する期間(日本時間。開始は含み、終了は含まない)。期間外のブロックは、公開側に返すとき
 *   (BuilderPresenter::forPublic())に取り除く(始まる前の内容を漏らさない)。エディタの Canvas では期間に関係なく表示する。
 */
final class Visibility
{
    /**
     * 表示しない端末に選べる値(エディタの端末と同じ並び)。
     *
     * @var list<string>
     */
    public const DEVICES = ['desktop', 'tablet', 'mobile'];

    /**
     * 期間の日時の形(エディタの datetime-local の値と同じ)。
     */
    public const DATETIME_FORMAT = 'Y-m-d\TH:i';

    /**
     * 表示条件の誤りの一覧(空なら正しい)。文言には対象のブロックの名前(label)を入れる。
     *
     * @return list<string>
     */
    public static function errors(mixed $visibility, string $label): array
    {
        $replace = ['block' => $label];

        if (! is_array($visibility) || ($visibility !== [] && array_is_list($visibility))) {
            return [__('「:block」の表示条件の形式が正しくありません。', $replace)];
        }

        $errors = [];

        foreach (array_diff(array_keys($visibility), ['hideOn', 'startAt', 'endAt']) as $key) {
            $errors[] = __('「:block」の表示条件に「:name」という項目はありません。', [...$replace, 'name' => $key]);
        }

        $hideOn = $visibility['hideOn'] ?? [];

        if (! is_array($hideOn) || ! array_is_list($hideOn) || array_diff($hideOn, self::DEVICES) !== [] || count(array_unique($hideOn)) !== count($hideOn)) {
            $errors[] = __('「:block」の表示しない端末の値が正しくありません。', $replace);
        } elseif (count($hideOn) === count(self::DEVICES)) {
            $errors[] = __('「:block」を、すべての端末で表示しないようにはできません。', $replace);
        }

        $startAt = self::parse($visibility['startAt'] ?? null);
        $endAt = self::parse($visibility['endAt'] ?? null);

        if ($startAt === false) {
            $errors[] = __('「:block」の表示を始める日時が正しくありません。', $replace);
        }

        if ($endAt === false) {
            $errors[] = __('「:block」の表示を終える日時が正しくありません。', $replace);
        }

        if ($startAt instanceof CarbonInterface && $endAt instanceof CarbonInterface && $startAt->gte($endAt)) {
            $errors[] = __('「:block」の表示を終える日時は、表示を始める日時より後にしてください。', $replace);
        }

        return $errors;
    }

    /**
     * 検証済みの表示条件から、指定していない項目を除いたもの(何も指定していなければ null。ノードに書かない)。
     * 表示しない端末は DEVICES の並びにそろえる。
     *
     * @param  array<string, mixed>|null  $visibility
     * @return array{hideOn?: list<string>, startAt?: string, endAt?: string}|null
     */
    public static function normalize(?array $visibility): ?array
    {
        $normalized = [];
        $hideOn = array_values(array_intersect(self::DEVICES, $visibility['hideOn'] ?? []));

        if ($hideOn !== []) {
            $normalized['hideOn'] = $hideOn;
        }

        foreach (['startAt', 'endAt'] as $key) {
            if (is_string($visibility[$key] ?? null)) {
                $normalized[$key] = $visibility[$key];
            }
        }

        return $normalized === [] ? null : $normalized;
    }

    /**
     * ノードが今(now)表示する期間の中か(期間を指定していなければ常に true)。
     *
     * @param  array<string, mixed>  $node
     */
    public static function isWithinPeriod(array $node, ?CarbonInterface $now = null): bool
    {
        $now ??= Date::now();
        $startAt = self::parse($node['visibility']['startAt'] ?? null);
        $endAt = self::parse($node['visibility']['endAt'] ?? null);

        return ! ($startAt instanceof CarbonInterface && $now->lt($startAt))
            && ! ($endAt instanceof CarbonInterface && $now->gte($endAt));
    }

    /**
     * 期間の日時(日本時間)。null は指定なし、false は形が正しくない。
     */
    private static function parse(mixed $value): CarbonInterface|false|null
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value) || preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}\z/', $value) !== 1) {
            return false;
        }

        $date = Date::createFromFormat('!'.self::DATETIME_FORMAT, $value);

        // 2026-02-30T25:00 のように繰り上がる値は受け付けない
        return $date instanceof CarbonInterface && $date->format(self::DATETIME_FORMAT) === $value ? $date : false;
    }
}
