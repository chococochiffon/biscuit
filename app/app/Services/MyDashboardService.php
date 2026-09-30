<?php

namespace App\Services;

use App\Enums\ArticleApprovalStatus;
use App\Enums\AuditAction;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\GalleryImage;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * マイページ(chococo)のダッシュボードに出す、ログイン中のユーザー自身の記事・ギャラリーの画像の状況・最近の操作・アカウント・画像をまとめる。
 * 管理画面のダッシュボード(DashboardService)の項目を、ユーザー本人のものだけに絞って返す。サイト全体やほかのユーザーの数字は返さない。
 */
class MyDashboardService
{
    /**
     * 最近ログインした日時の件数。
     */
    public const RECENT_LOGIN_LIMIT = 3;

    public function __construct(private readonly User $user) {}

    /**
     * 記事とギャラリーの画像の件数を状態ごとに数える。非公開は公開中と予約公開以外のすべて(下書き・承認待ち・公開終了など)。
     *
     * @return array{articles: array{total: int, published: int, scheduled: int, draft: int, pending: int, unpublished: int}, gallery_images: array{total: int, published: int, draft: int, pending: int}}
     */
    public function contentCounts(): array
    {
        $articleTotal = $this->articles()->count();
        $articlePublished = $this->articles()->published()->count();
        $articleScheduled = $this->scheduledArticles()->count();

        return [
            'articles' => [
                'total' => $articleTotal,
                'published' => $articlePublished,
                'scheduled' => $articleScheduled,
                'draft' => $this->articles()->where('approval', ArticleApprovalStatus::Draft)->count(),
                'pending' => $this->articles()->where('approval', ArticleApprovalStatus::Pending)->count(),
                'unpublished' => $articleTotal - $articlePublished - $articleScheduled,
            ],
            'gallery_images' => [
                'total' => $this->galleryImages()->count(),
                'published' => $this->galleryImages()->where('approval', ArticleApprovalStatus::Published)->count(),
                'draft' => $this->galleryImages()->where('approval', ArticleApprovalStatus::Draft)->count(),
                'pending' => $this->galleryImages()->where('approval', ArticleApprovalStatus::Pending)->count(),
            ],
        ];
    }

    /**
     * 最近編集した記事とギャラリーの画像を、更新日時の新しい順に返す。status は ContentStatus の値(ギャラリーは draft・pending・published)。
     *
     * @return Collection<int, array{type: string, id: int, title: string, status: string, updated_at: string|null}>
     */
    public function recentContents(): Collection
    {
        $limit = DashboardService::RECENT_LIMIT;

        return $this->articles()->latest('updated_at')->latest('id')->limit($limit)->get()
            ->map(fn (Article $article) => [
                'type' => 'article',
                'id' => $article->id,
                'title' => $article->title,
                'status' => $article->contentStatus()->value,
                'updated_at' => $article->updated_at,
            ])
            ->concat($this->galleryImages()->latest('updated_at')->latest('id')->limit($limit)->get()
                ->map(fn (GalleryImage $image) => [
                    'type' => 'gallery_image',
                    'id' => $image->id,
                    'title' => $image->name,
                    'status' => $image->approval->value,
                    'updated_at' => $image->updated_at,
                ]))
            ->sortByDesc(fn (array $row) => $row['updated_at']?->getTimestamp() ?? 0)
            ->take($limit)
            ->map(fn (array $row) => [...$row, 'updated_at' => $row['updated_at']?->toIso8601String()])
            ->values();
    }

    /**
     * 予約公開の記事を、今日公開するものと今週(明日から DashboardService::UPCOMING_DAYS 日以内)公開予定のものに分けて、公開開始日時の早い順に返す。
     *
     * @return array{today: Collection<int, array{id: int, title: string, publish_at: string}>, this_week: Collection<int, array{id: int, title: string, publish_at: string}>}
     */
    public function scheduledContents(): array
    {
        $endOfToday = now()->endOfDay();

        $articles = $this->scheduledArticles()
            ->where('publication_start_datetime', '<=', now()->addDays(DashboardService::UPCOMING_DAYS)->endOfDay())
            ->orderBy('publication_start_datetime')
            ->orderBy('id')
            ->get();

        $rows = fn (Collection $articles) => $articles
            ->take(DashboardService::SCHEDULED_LIMIT)
            ->map(fn (Article $article) => [
                'id' => $article->id,
                'title' => $article->title,
                'publish_at' => $article->publication_start_datetime->toIso8601String(),
            ])
            ->values();

        return [
            'today' => $rows($articles->filter(fn (Article $article) => $article->publication_start_datetime->lte($endOfToday))),
            'this_week' => $rows($articles->filter(fn (Article $article) => $article->publication_start_datetime->gt($endOfToday))),
        ];
    }

    /**
     * コンテンツの注意事項のうち、該当するものがあるものだけを返す。
     * returned は管理者に差し戻された(差し戻しの理由がある下書きの)記事・画像、no_thumbnail は公開中・予約公開なのにサムネイル未設定の記事、pending は承認待ちの記事・画像。
     *
     * @return list<array{key: string, count: int, items: Collection<int, array{type: string, id: int, title: string}>}>
     */
    public function contentWarnings(): array
    {
        $returned = fn (Builder|HasMany $query) => $query
            ->where('approval', ArticleApprovalStatus::Draft)
            ->whereNotNull('review_comment')
            ->where('review_comment', '!=', '');

        $noThumbnail = $this->articles()
            ->where('approval', ArticleApprovalStatus::Published)
            ->notEnded()
            ->where(fn (Builder $query) => $query
                ->whereNull('thumbnail')
                ->orWhere('thumbnail', '')
                ->orWhere('thumbnail', Article::DEFAULT_THUMBNAIL_PATH));

        $pending = fn (Builder|HasMany $query) => $query->where('approval', ArticleApprovalStatus::Pending);

        $warnings = [
            ['key' => 'returned', 'articles' => $returned($this->articles()), 'gallery_images' => $returned($this->galleryImages())],
            ['key' => 'no_thumbnail', 'articles' => $noThumbnail, 'gallery_images' => null],
            ['key' => 'pending', 'articles' => $pending($this->articles()), 'gallery_images' => $pending($this->galleryImages())],
        ];

        return collect($warnings)
            ->map(fn (array $warning) => [
                'key' => $warning['key'],
                'count' => $warning['articles']->count() + ($warning['gallery_images']?->count() ?? 0),
                'items' => $this->warningItems($warning['articles'], 'article')
                    ->concat($warning['gallery_images'] ? $this->warningItems($warning['gallery_images'], 'gallery_image') : [])
                    ->values(),
            ])
            ->filter(fn (array $warning) => $warning['count'] > 0)
            ->values()
            ->all();
    }

    /**
     * 自分の最近の操作(操作ログで操作者が自分のもの)を新しい順に返す。IP アドレスなどは返さない。
     * ログインまわりの記録は毎回残って操作が埋もれるため除く(最近のログインは account() で返す)。
     *
     * @return Collection<int, array{action: string, action_label: string, subject_type: string|null, subject_type_label: string|null, subject_label: string|null, created_at: string|null}>
     */
    public function recentActivities(): Collection
    {
        return $this->ownAuditLogs()
            ->whereNotIn('action', [AuditAction::Login, AuditAction::Logout, AuditAction::LoginCodeSent])
            ->limit(DashboardService::RECENT_LIMIT)
            ->get()
            ->map(fn (AuditLog $log) => [
                'action' => $log->action->value,
                'action_label' => $log->action->label(),
                'subject_type' => $log->subject_type,
                'subject_type_label' => $log->subjectTypeLabel(),
                'subject_label' => $log->subject_label,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);
    }

    /**
     * アカウントの状況(承認なしで公開できるか・投稿者ページを公開しているか・最近ログインした日時)。
     *
     * @return array{skip_approval: bool, public_profile: bool, profile_path: string|null, recent_logins: list<string|null>}
     */
    public function account(): array
    {
        $this->user->loadMissing('detail');

        return [
            'skip_approval' => (bool) $this->user->skip_approval,
            'public_profile' => $this->user->hasPublicProfile(),
            'profile_path' => $this->user->authorProfilePath(),
            'recent_logins' => $this->ownAuditLogs()
                ->where('action', AuditAction::Login)
                ->limit(self::RECENT_LOGIN_LIMIT)
                ->pluck('created_at')
                ->map(fn (?CarbonInterface $at) => $at?->toIso8601String())
                ->all(),
        ];
    }

    /**
     * 自分がアップロードした画像(記事のサムネイル・ギャラリーの画像・アイコン)の件数と容量。
     * 記事本文の画像は投稿者を記録していないため数えない。デフォルトのサムネイルは数えない。
     *
     * @return array{count: int, bytes: int, groups: list<array{key: string, count: int, bytes: int}>}
     */
    public function media(): array
    {
        $groups = [
            'thumbnail' => $this->articles()->whereNotNull('thumbnail')->where('thumbnail', '!=', Article::DEFAULT_THUMBNAIL_PATH)->pluck('thumbnail'),
            'gallery' => $this->galleryImages()->pluck('image'),
            'icon' => collect([$this->user->detail?->user_image]),
        ];

        $disk = Storage::disk('public');
        $result = [];

        foreach ($groups as $key => $paths) {
            $existing = $paths->filter(fn (?string $path) => filled($path) && $disk->exists($path))->unique();
            $result[] = ['key' => $key, 'count' => $existing->count(), 'bytes' => $existing->sum(fn (string $path) => $disk->size($path))];
        }

        return [
            'count' => array_sum(array_column($result, 'count')),
            'bytes' => array_sum(array_column($result, 'bytes')),
            'groups' => $result,
        ];
    }

    /**
     * @return HasMany<Article, User>
     */
    private function articles(): HasMany
    {
        return $this->user->articles();
    }

    /**
     * @return HasMany<GalleryImage, User>
     */
    private function galleryImages(): HasMany
    {
        return $this->user->galleryImages();
    }

    /**
     * 予約公開の記事(公開ステータスが「公開」で、公開開始日時が未来)。
     *
     * @return HasMany<Article, User>
     */
    private function scheduledArticles(): HasMany
    {
        return $this->articles()->where('approval', ArticleApprovalStatus::Published)->upcoming();
    }

    /**
     * 操作者が自分の操作ログ(新しい順)。
     *
     * @return Builder<AuditLog>
     */
    private function ownAuditLogs(): Builder
    {
        return AuditLog::query()
            ->where('actor_type', 'user')
            ->where('actor_id', $this->user->id)
            ->newest();
    }

    /**
     * 注意事項に並べる記事・画像(更新日時の新しい順に DashboardService::WARNING_ITEM_LIMIT 件)。
     *
     * @return Collection<int, array{type: string, id: int, title: string}>
     */
    private function warningItems(Builder|HasMany $query, string $type): Collection
    {
        return (clone $query)->latest('updated_at')->latest('id')->limit(DashboardService::WARNING_ITEM_LIMIT)->get()
            ->map(fn (Article|GalleryImage $content) => [
                'type' => $type,
                'id' => $content->id,
                'title' => $content instanceof Article ? $content->title : $content->name,
            ]);
    }
}
