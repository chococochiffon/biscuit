<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * 更新できる Biscuit のバージョンを、GitHub のリリース(config('biscuit.update_check.repository') の最新のリリース)で確かめる。
 * 問い合わせの結果はキャッシュし(成功・リリースなしは cache_hours 時間、失敗は failure_cache_minutes 分)、
 * 管理画面の上部のお知らせはキャッシュだけを見る(画面を開くたびに GitHub に問い合わせない)。
 * メールで知らせたバージョンは storage のファイルに残し、同じバージョンでは 2 回送らない。
 */
class UpdateCheckService
{
    public const CACHE_KEY = 'biscuit.update_check.latest_release';

    /**
     * メールで知らせたバージョンを残すファイル(local ディスク基準)。
     */
    public const NOTIFIED_FILE = 'biscuit/update-check.json';

    /**
     * リリースのタグの形(v1.2.3。v は省略可、-beta などの後ろの付記も可)。
     */
    private const VERSION_PATTERN = '/\Av?(\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?)\z/';

    /**
     * 今のバージョンより新しいリリース(なければ・確かめない設定なら null)。fetch が false ならキャッシュだけを見る。
     *
     * @return array{version: string, name: string, url: string, published_at: CarbonImmutable|null, notes: string}|null
     */
    public function availableUpdate(bool $fetch = true): ?array
    {
        if (! config('biscuit.update_check.enabled')) {
            return null;
        }

        $release = $fetch ? $this->latestRelease() : $this->cachedRelease();

        return $release !== null && version_compare($release['version'], (string) config('biscuit.version'), '>') ? $release : null;
    }

    /**
     * 最新のリリース(キャッシュが切れていれば GitHub に問い合わせる)。リリースがない・問い合わせに失敗したら null。
     *
     * @return array{version: string, name: string, url: string, published_at: CarbonImmutable|null, notes: string}|null
     */
    public function latestRelease(): ?array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached) && array_key_exists('release', $cached)) {
            return self::hydrate($cached['release']);
        }

        return $this->refresh();
    }

    /**
     * キャッシュを使わずに GitHub に問い合わせ、結果をキャッシュして返す。
     *
     * @return array{version: string, name: string, url: string, published_at: CarbonImmutable|null, notes: string}|null
     */
    public function refresh(): ?array
    {
        try {
            $release = $this->fetch();
            Cache::put(self::CACHE_KEY, ['release' => $release], now()->addHours((int) config('biscuit.update_check.cache_hours')));
        } catch (Throwable $exception) {
            // 問い合わせに失敗しても管理画面は止めない(しばらくは問い合わせ直さない)
            Log::warning('Biscuit の更新の確認に失敗しました: '.$exception->getMessage());
            $release = null;
            Cache::put(self::CACHE_KEY, ['release' => null], now()->addMinutes((int) config('biscuit.update_check.failure_cache_minutes')));
        }

        return self::hydrate($release);
    }

    /**
     * メールでまだ知らせていないバージョンか。
     */
    public function shouldNotify(string $version): bool
    {
        $notified = json_decode((string) Storage::disk('local')->get(self::NOTIFIED_FILE), true);

        return ($notified['version'] ?? null) !== $version;
    }

    /**
     * メールで知らせたバージョンを残す。
     */
    public function markNotified(string $version): void
    {
        Storage::disk('local')->put(self::NOTIFIED_FILE, json_encode(['version' => $version, 'notified_at' => now()->toIso8601String()]));
    }

    /**
     * キャッシュにある最新のリリース(まだ確かめていなければ null)。
     *
     * @return array{version: string, name: string, url: string, published_at: CarbonImmutable|null, notes: string}|null
     */
    private function cachedRelease(): ?array
    {
        $cached = Cache::get(self::CACHE_KEY);

        return is_array($cached) ? self::hydrate($cached['release'] ?? null) : null;
    }

    /**
     * キャッシュに入れた形(日時は ISO 8601 の文字列。キャッシュはオブジェクトを復元しない設定のため)を、返す形にする。
     *
     * @param  array{version: string, name: string, url: string, published_at: string|null, notes: string}|null  $release
     * @return array{version: string, name: string, url: string, published_at: CarbonImmutable|null, notes: string}|null
     */
    private static function hydrate(?array $release): ?array
    {
        if ($release === null) {
            return null;
        }

        $publishedAt = $release['published_at'] ?? null;

        return [...$release, 'published_at' => is_string($publishedAt) ? CarbonImmutable::parse($publishedAt)->setTimezone(config('app.timezone')) : null];
    }

    /**
     * GitHub の API で最新のリリースを取る(下書き・プレリリースは除かれる)。リリースがない(404)・タグの形が違えば null。
     * キャッシュに入れるため、日時は文字列のまま返す。
     *
     * @return array{version: string, name: string, url: string, published_at: string|null, notes: string}|null
     */
    private function fetch(): ?array
    {
        $repository = (string) config('biscuit.update_check.repository');
        $response = Http::timeout(5)
            ->acceptJson()
            ->withHeaders(['User-Agent' => 'biscuit/'.config('biscuit.version')])
            ->get("https://api.github.com/repos/{$repository}/releases/latest");

        if ($response->status() === 404) {
            return null;
        }

        $response->throw();

        if (preg_match(self::VERSION_PATTERN, (string) $response->json('tag_name'), $matches) !== 1) {
            return null;
        }

        $url = (string) $response->json('html_url');
        $publishedAt = $response->json('published_at');

        return [
            'version' => $matches[1],
            'name' => (string) ($response->json('name') ?: $response->json('tag_name')),
            // 管理画面に出すリンクは GitHub のリリースのページだけにする
            'url' => str_starts_with($url, 'https://github.com/') ? $url : "https://github.com/{$repository}/releases",
            'published_at' => is_string($publishedAt) ? $publishedAt : null,
            'notes' => mb_strimwidth(trim((string) $response->json('body')), 0, 1000, '…'),
        ];
    }
}
