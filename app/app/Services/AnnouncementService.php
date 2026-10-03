<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Biscuit の開発元が配信するお知らせ(config('biscuit.announcements.url') の announcements.json)を読み、管理画面に出す形にする。
 *
 * ファイルの形: { "announcements": [ { "id", "date"(YYYY-MM-DD), "level"(info・important・security), "title", "body"?, "url"?,
 * "versions"?(例: <1.2.3。このサイトのバージョンが当てはまるときだけ出す), "expires"?(YYYY-MM-DD。この日を過ぎたら出さない) } ] }。
 * title・body は文字列か { "ja": …, "en": … }(管理画面の言語で出し分け、なければ ja)。形の正しくないお知らせは出さない。
 *
 * 読んだ結果はキャッシュし(成功は cache_hours 時間、失敗は failure_cache_minutes 分)、管理画面の上部のお知らせはキャッシュだけを見る。
 * 題名・本文はただの文字として出し(Blade でエスケープ)、リンクは https:// だけにする。メールで知らせたお知らせの id は storage のファイルに残す。
 */
class AnnouncementService
{
    public const CACHE_KEY = 'biscuit.announcements';

    /**
     * メールで知らせたお知らせの id を残すファイル(local ディスク基準)。
     */
    public const NOTIFIED_FILE = 'biscuit/announcements-notified.json';

    /**
     * 重要・セキュリティのお知らせの重要度(管理画面の上部とメールで知らせる)。
     *
     * @var list<string>
     */
    public const URGENT_LEVELS = ['important', 'security'];

    private const LEVELS = ['info', 'important', 'security'];

    private const ID_PATTERN = '/\A[A-Za-z0-9_.-]{1,100}\z/';

    private const DATE_PATTERN = '/\A\d{4}-\d{2}-\d{2}\z/';

    private const VERSIONS_PATTERN = '/\A(<=|>=|<|>|=)?\s*v?(\d+\.\d+\.\d+)\z/';

    /**
     * このサイトに出すお知らせ(期限内で、対象のバージョンが当てはまるもの。新しい順)。fetch が false ならキャッシュだけを見る。
     *
     * @return list<array{id: string, date: CarbonImmutable, level: string, title: string, body: string, url: string|null}>
     */
    public function current(bool $fetch = true): array
    {
        if (! config('biscuit.announcements.enabled')) {
            return [];
        }

        $announcements = $fetch ? $this->load() : (Cache::get(self::CACHE_KEY)['announcements'] ?? []);
        $today = Date::today()->format('Y-m-d');
        $version = (string) config('biscuit.version');

        $current = array_filter($announcements, fn (array $announcement) => ($announcement['expires'] === null || $announcement['expires'] >= $today)
            && ($announcement['versions'] === null || version_compare($version, $announcement['versions'][1], $announcement['versions'][0])));

        usort($current, fn (array $a, array $b) => [$b['date'], $b['id']] <=> [$a['date'], $a['id']]);

        return array_map($this->present(...), $current);
    }

    /**
     * 重要・セキュリティのお知らせ(管理画面の上部・メール)。
     *
     * @return list<array{id: string, date: CarbonImmutable, level: string, title: string, body: string, url: string|null}>
     */
    public function urgent(bool $fetch = true): array
    {
        return array_values(array_filter($this->current($fetch), fn (array $announcement) => in_array($announcement['level'], self::URGENT_LEVELS, true)));
    }

    /**
     * キャッシュを使わずに読み直す。
     */
    public function refresh(): void
    {
        try {
            $response = Http::timeout(5)->acceptJson()->withHeaders(['User-Agent' => 'biscuit/'.config('biscuit.version')])
                ->get((string) config('biscuit.announcements.url'))
                ->throw();
            $items = json_decode($response->body(), true)['announcements'] ?? [];
            $announcements = array_values(array_filter(array_map($this->normalize(...), is_array($items) ? $items : [])));
            Cache::put(self::CACHE_KEY, ['announcements' => $announcements], now()->addHours((int) config('biscuit.announcements.cache_hours')));
        } catch (Throwable $exception) {
            // 読めなくても管理画面は止めない(しばらくは読み直さない)
            Log::warning('Biscuit からのお知らせの読み込みに失敗しました: '.$exception->getMessage());
            Cache::put(self::CACHE_KEY, ['announcements' => []], now()->addMinutes((int) config('biscuit.announcements.failure_cache_minutes')));
        }
    }

    /**
     * メールでまだ知らせていないお知らせか。
     */
    public function shouldNotify(string $id): bool
    {
        return ! in_array($id, $this->notifiedIds(), true);
    }

    /**
     * メールで知らせたお知らせを残す。
     */
    public function markNotified(string $id): void
    {
        Storage::disk('local')->put(self::NOTIFIED_FILE, json_encode(array_values(array_unique([...$this->notifiedIds(), $id]))));
    }

    /**
     * @return list<string>
     */
    private function notifiedIds(): array
    {
        $ids = json_decode((string) Storage::disk('local')->get(self::NOTIFIED_FILE), true);

        return is_array($ids) ? array_values(array_filter($ids, is_string(...))) : [];
    }

    /**
     * キャッシュにあるお知らせ(切れていれば読み直す)。
     *
     * @return list<array<string, mixed>>
     */
    private function load(): array
    {
        if (! is_array(Cache::get(self::CACHE_KEY))) {
            $this->refresh();
        }

        return Cache::get(self::CACHE_KEY)['announcements'] ?? [];
    }

    /**
     * ファイルのお知らせ 1 件を、キャッシュに入れる形(文字列と配列だけ)にする。形が正しくなければ null。
     *
     * @return array{id: string, date: string, level: string, title: array<string, string>, body: array<string, string>, url: string|null, versions: array{0: string, 1: string}|null, expires: string|null}|null
     */
    private function normalize(mixed $item): ?array
    {
        if (! is_array($item)) {
            return null;
        }

        $id = $item['id'] ?? null;
        $date = $item['date'] ?? null;
        $level = $item['level'] ?? 'info';
        $title = $this->texts($item['title'] ?? null);
        $url = $item['url'] ?? null;
        $expires = $item['expires'] ?? null;

        if (! is_string($id) || preg_match(self::ID_PATTERN, $id) !== 1 || ! $this->isDate($date) || ! in_array($level, self::LEVELS, true) || $title === []) {
            return null;
        }

        if (($url !== null && (! is_string($url) || ! str_starts_with($url, 'https://') || preg_match('/\s/', $url) === 1)) || ($expires !== null && ! $this->isDate($expires))) {
            return null;
        }

        $versions = null;

        if (isset($item['versions'])) {
            if (! is_string($item['versions']) || preg_match(self::VERSIONS_PATTERN, trim($item['versions']), $matches) !== 1) {
                return null;
            }

            $versions = [$matches[1] === '' ? '=' : $matches[1], $matches[2]];
        }

        return [
            'id' => $id,
            'date' => $date,
            'level' => $level,
            'title' => $title,
            'body' => $this->texts($item['body'] ?? null),
            'url' => $url,
            'versions' => $versions,
            'expires' => $expires,
        ];
    }

    /**
     * 題名・本文(文字列か、言語 → 文字列)を、言語 → 文字列にする(文字列だけのときは ja)。
     *
     * @return array<string, string>
     */
    private function texts(mixed $value): array
    {
        $texts = is_string($value) ? ['ja' => $value] : (is_array($value) ? $value : []);

        return array_filter(
            array_map(fn (mixed $text) => is_string($text) ? mb_strimwidth(trim($text), 0, 2000, '…') : '', $texts),
            fn (string $text, mixed $locale) => is_string($locale) && $text !== '',
            ARRAY_FILTER_USE_BOTH,
        );
    }

    private function isDate(mixed $value): bool
    {
        return is_string($value) && preg_match(self::DATE_PATTERN, $value) === 1 && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4));
    }

    /**
     * 管理画面に出す形(題名・本文は今の言語。なければ ja、それもなければ最初のもの)。
     *
     * @param  array{id: string, date: string, level: string, title: array<string, string>, body: array<string, string>, url: string|null}  $announcement
     * @return array{id: string, date: CarbonImmutable, level: string, title: string, body: string, url: string|null}
     */
    private function present(array $announcement): array
    {
        $text = fn (array $texts) => $texts[App::getLocale()] ?? $texts['ja'] ?? (reset($texts) ?: '');

        return [
            'id' => $announcement['id'],
            'date' => CarbonImmutable::parse($announcement['date']),
            'level' => $announcement['level'],
            'title' => $text($announcement['title']),
            'body' => $text($announcement['body']),
            'url' => $announcement['url'],
        ];
    }
}
