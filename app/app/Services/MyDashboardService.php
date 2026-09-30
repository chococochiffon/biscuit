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

    public function __construct(
        private readonly User $user,
        private readonly BrokenLinkService $brokenLinks,
    ) {}

    /**
     * 記事とギャラリーの画像の件数を状態ごとに数える。非公開は公開中と予約公開以外のすべて(下書き・承認待ち・公開終了など)。
     *
     * @return array{articles: array{total: int, published: int, scheduled: int, draft: int, pending: int, unpublished: int}, gallery_images: array{total: int, published: int, draft: int, pending: int}}
     */
    public function contentCounts(): array
    {
        return [
            'articles' => DashboardService::countArticles(fn () => $this->articles()),
            'gallery_images' => [
                'total' => $this->galleryImages()->count(),
                'published' => $this->galleryImages()->withApproval(ArticleApprovalStatus::Published)->count(),
                'draft' => $this->galleryImages()->withApproval(ArticleApprovalStatus::Draft)->count(),
                'pending' => $this->galleryImages()->withApproval(ArticleApprovalStatus::Pending)->count(),
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
        $articles = $this->articles()
            ->scheduled()
            ->where('publication_start_datetime', '<=', DashboardService::scheduledUntil())
            ->orderBy('publication_start_datetime')
            ->orderBy('id')
            ->get();

        return array_map(
            fn (Collection $articles) => $articles->map(fn (Article $article) => [
                'id' => $article->id,
                'title' => $article->title,
                'publish_at' => $article->publication_start_datetime->toIso8601String(),
            ]),
            DashboardService::splitScheduled($articles),
        );
    }

    /**
     * コンテンツの注意事項のうち、該当するものがあるものだけを返す。
     * returned は管理者に差し戻された(差し戻しの理由がある下書きの)記事・画像、broken_links は本文にリンク切れのある記事(items に切れているリンク links も入れる)、
     * no_thumbnail は公開中・予約公開なのにサムネイル未設定の記事、pending は承認待ちの記事・画像。
     *
     * @return list<array{key: string, count: int, items: Collection<int, array{type: string, id: int, title: string, links?: list<string>}>}>
     */
    public function contentWarnings(): array
    {
        $warning = fn (string $key, Builder|HasMany $articles, Builder|HasMany|null $galleryImages = null) => [
            'key' => $key,
            'count' => $articles->count() + ($galleryImages?->count() ?? 0),
            'items' => $this->warningItems($articles, 'article')
                ->concat($galleryImages ? $this->warningItems($galleryImages, 'gallery_image') : [])
                ->values(),
        ];

        $brokenLinks = $this->brokenLinks->inArticles($this->articles());

        $warnings = [
            $warning('returned', $this->articles()->returned(), $this->galleryImages()->returned()),
            [
                'key' => 'broken_links',
                'count' => $brokenLinks->count(),
                'items' => $brokenLinks->take(DashboardService::WARNING_ITEM_LIMIT)->map(fn (array $row) => [
                    'type' => 'article',
                    'id' => $row['article']->id,
                    'title' => $row['article']->title,
                    'links' => $row['links'],
                ])->values(),
            ],
            $warning('no_thumbnail', $this->articles()->missingThumbnail()),
            $warning('pending', $this->articles()->withApproval(ArticleApprovalStatus::Pending), $this->galleryImages()->withApproval(ArticleApprovalStatus::Pending)),
        ];

        return array_values(array_filter($warnings, fn (array $warning) => $warning['count'] > 0));
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
