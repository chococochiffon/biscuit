<?php

namespace App\Services;

use App\Models\PageView;
use App\Support\PublicPage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * PV(公開側のページが表示された記録)を記録する。
 * 公開側の表示は chococo が受け持つため、PV は chococo のサーバーが POST /api/page-views へ中継し(API\PageViewController)、
 * 閲覧者の IP アドレス・User-Agent・Referer・訪問者の識別子・セッションの識別子はリクエストの本文で受け取る
 * (中継元が chococo であることは、フォームリクエストが共有の鍵で確かめる)。
 * 保存は store() にまとめてあり、キューや集計テーブルへ移すときはここだけを変える。
 */
class PageViewService
{
    /**
     * ページの表示を 1 PV として記録し、訪問者の識別子を返す(chococo はこれを Cookie に入れて次から送る)。
     * Bot の User-Agent や、一定時間内の同じ訪問者・同じパスの重複アクセスは記録しない(識別子は返す)。
     */
    public function record(Request $request, PublicPage $page): string
    {
        $visitorId = $this->visitorId($request);
        $userAgent = $this->userAgent($request);

        if ($this->isExcludedUserAgent($userAgent) || $this->isDuplicate($visitorId, $page->path)) {
            return $visitorId;
        }

        $this->store([
            'content_type' => $page->contentType(),
            'content_id' => $page->contentId(),
            'path' => $page->path,
            'user_id' => $request->user('sanctum')?->getAuthIdentifier(),
            'visitor_id' => $visitorId,
            'session_id' => $this->sessionHash($request),
            'ip_hash' => $this->ipHash($this->clientIp($request)),
            'user_agent' => $userAgent,
            'referer' => $this->referer($request),
            'viewed_at' => now(),
        ]);

        return $visitorId;
    }

    /**
     * 訪問者の識別子。送られた値が UUID でなければ(初回のアクセスなど)新しく発行する。
     */
    public function visitorId(Request $request): string
    {
        $visitorId = $request->input('visitor_id');

        return is_string($visitorId) && Str::isUuid($visitorId) ? Str::lower($visitorId) : (string) Str::uuid();
    }

    /**
     * セッションの識別子のハッシュ(送られなければ null)。識別子そのものは保存しない。
     */
    public function sessionHash(Request $request): ?string
    {
        $sessionId = $request->input('session_id');

        return is_string($sessionId) && $sessionId !== '' ? $this->hash($sessionId) : null;
    }

    /**
     * IP アドレスのハッシュ(IPv4・IPv6 とも同じ書き方にそろえてからハッシュにする)。IP アドレスそのものは保存しない。
     */
    public function ipHash(?string $ip): ?string
    {
        if ($ip === null || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        return $this->hash((string) inet_ntop((string) inet_pton($ip)));
    }

    /**
     * 閲覧者の IP アドレス(chococo が送った値。なければ接続元)。
     */
    private function clientIp(Request $request): ?string
    {
        return $request->input('ip') ?: $request->ip();
    }

    /**
     * 閲覧者の User-Agent(chococo が送った値。なければリクエストのもの)。長すぎる値は切り詰める。
     */
    private function userAgent(Request $request): ?string
    {
        $userAgent = $request->input('user_agent') ?: $request->userAgent();

        return is_string($userAgent) && $userAgent !== '' ? mb_substr($userAgent, 0, 512) : null;
    }

    /**
     * 閲覧者のブラウザが送った Referer(chococo が送った値)。長すぎる値は切り詰める。
     */
    private function referer(Request $request): ?string
    {
        $referer = $request->input('referer');

        return is_string($referer) && $referer !== '' ? mb_substr($referer, 0, 2048) : null;
    }

    /**
     * Bot などの記録しない User-Agent か(config/page_views.php の excluded_user_agents)。
     */
    private function isExcludedUserAgent(?string $userAgent): bool
    {
        if ($userAgent === null) {
            return false;
        }

        foreach (config('page_views.excluded_user_agents', []) as $pattern) {
            if (preg_match($pattern, $userAgent) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * 同じ訪問者が同じパスを一定時間内(config/page_views.php の duplicate_window_seconds。0 なら判定しない)に表示済みか。
     */
    private function isDuplicate(string $visitorId, string $path): bool
    {
        $seconds = (int) config('page_views.duplicate_window_seconds');

        return $seconds > 0 && PageView::query()
            ->where('visitor_id', $visitorId)
            ->where('viewed_at', '>=', now()->subSeconds($seconds))
            ->where('path', $path)
            ->exists();
    }

    /**
     * PV を保存する。
     *
     * @param  array<string, mixed>  $attributes
     */
    private function store(array $attributes): void
    {
        PageView::query()->create($attributes);
    }

    private function hash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
