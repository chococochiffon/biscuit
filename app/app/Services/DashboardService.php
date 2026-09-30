<?php

namespace App\Services;

use App\Enums\ArticleApprovalStatus;
use App\Models\Administrator;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\GalleryImage;
use App\Models\SinglePage;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * 管理画面のダッシュボードに出す、コンテンツの状況・最近編集したコンテンツ・予約公開・コンテンツの注意事項・最近の操作をまとめる。
 * 対象は記事と固定ページ(日時はどれも現在時刻を基準にする)。
 */
class DashboardService
{
    /**
     * 最近編集したコンテンツ・最近の操作の件数。
     */
    public const RECENT_LIMIT = 8;

    /**
     * 予約公開の一覧の件数。
     */
    public const SCHEDULED_LIMIT = 10;

    /**
     * コンテンツの注意事項ごとに、編集へのリンクとして並べる件数。
     */
    public const WARNING_ITEM_LIMIT = 5;

    /**
     * 「今週公開予定」に含める日数(明日から数える)。
     */
    public const UPCOMING_DAYS = 7;

    /**
     * 記事と固定ページの件数を状態ごとに数える。
     * 非公開は、公開中と予約公開以外のすべて(記事は下書き・未承認・公開終了・公開開始日時の未設定、固定ページは公開終了)。
     *
     * @return array{article: array{total: int, published: int, scheduled: int, draft: int, pending: int, unpublished: int}, single_page: array{total: int, published: int, scheduled: int, unpublished: int}}
     */
    public function contentCounts(): array
    {
        $articleTotal = Article::query()->count();
        $articlePublished = Article::query()->published()->count();
        $articleScheduled = $this->scheduledArticles()->count();

        $pageTotal = SinglePage::query()->count();
        $pagePublished = SinglePage::query()->published()->count();
        $pageScheduled = SinglePage::query()->upcoming()->count();

        return [
            'article' => [
                'total' => $articleTotal,
                'published' => $articlePublished,
                'scheduled' => $articleScheduled,
                'draft' => Article::query()->where('approval', ArticleApprovalStatus::Draft)->count(),
                'pending' => Article::query()->where('approval', ArticleApprovalStatus::Pending)->count(),
                'unpublished' => $articleTotal - $articlePublished - $articleScheduled,
            ],
            'single_page' => [
                'total' => $pageTotal,
                'published' => $pagePublished,
                'scheduled' => $pageScheduled,
                'unpublished' => $pageTotal - $pagePublished - $pageScheduled,
            ],
        ];
    }

    /**
     * 最近編集した記事と固定ページを、更新日時の新しい順に返す。更新者は操作ログの最新の記録の操作者。
     *
     * @return Collection<int, array{type: string, title: string, status: string, status_color: string, updated_by: string|null, updated_at: CarbonInterface|null, edit_url: string}>
     */
    public function recentContents(): Collection
    {
        $articles = Article::query()->latest('updated_at')->latest('id')->limit(self::RECENT_LIMIT)->get();
        $pages = SinglePage::query()->latest('updated_at')->latest('id')->limit(self::RECENT_LIMIT)->get();

        $updaters = $this->latestActorNames([
            'article' => $articles->modelKeys(),
            'single_page' => $pages->modelKeys(),
        ]);

        return $articles->map(fn (Article $article) => $this->contentRow($article, 'article', $updaters))
            ->concat($pages->map(fn (SinglePage $page) => $this->contentRow($page, 'single_page', $updaters)))
            ->sortByDesc(fn (array $row) => $row['updated_at']?->getTimestamp() ?? 0)
            ->take(self::RECENT_LIMIT)
            ->values();
    }

    /**
     * 予約公開の記事と固定ページを、今日公開するものと今週(明日から UPCOMING_DAYS 日以内)公開予定のものに分けて、公開開始日時の早い順に返す。
     *
     * @return array{today: Collection<int, array{type: string, title: string, publish_at: CarbonInterface, edit_url: string}>, this_week: Collection<int, array{type: string, title: string, publish_at: CarbonInterface, edit_url: string}>}
     */
    public function scheduledContents(): array
    {
        $endOfToday = now()->endOfDay();
        $endOfWeek = now()->addDays(self::UPCOMING_DAYS)->endOfDay();

        $all = $this->scheduledArticles()
            ->where('publication_start_datetime', '<=', $endOfWeek)
            ->get()
            ->map(fn (Article $article) => $this->scheduledRow($article, 'article'))
            ->concat(SinglePage::query()->upcoming()
                ->where('publication_start_datetime', '<=', $endOfWeek)
                ->get()
                ->map(fn (SinglePage $page) => $this->scheduledRow($page, 'single_page')))
            ->sortBy(fn (array $row) => $row['publish_at']->getTimestamp())
            ->values();

        return [
            'today' => $all->filter(fn (array $row) => $row['publish_at']->lte($endOfToday))->take(self::SCHEDULED_LIMIT)->values(),
            'this_week' => $all->filter(fn (array $row) => $row['publish_at']->gt($endOfToday))->take(self::SCHEDULED_LIMIT)->values(),
        ];
    }

    /**
     * コンテンツの注意事項(公開中・予約公開なのにアイキャッチ未設定の記事、短文が未入力の固定ページ、承認待ちの記事・ギャラリー画像)のうち、該当するものがあるものだけを返す。
     *
     * @return list<array{label: string, count: int, url: string|null, items: Collection<int, array{title: string, edit_url: string}>}>
     */
    public function contentWarnings(): array
    {
        $noThumbnail = Article::query()
            ->where('approval', ArticleApprovalStatus::Published)
            ->notEnded()
            ->where(fn (Builder $query) => $query
                ->whereNull('thumbnail')
                ->orWhere('thumbnail', '')
                ->orWhere('thumbnail', Article::DEFAULT_THUMBNAIL_PATH));

        $noShortSentences = SinglePage::query()
            ->notEnded()
            ->where(fn (Builder $query) => $query->whereNull('short_sentences')->orWhere('short_sentences', ''));

        $warnings = [
            [
                'label' => __('公開中・予約公開なのにアイキャッチ(サムネイル)未設定の記事'),
                'count' => $noThumbnail->count(),
                'url' => null,
                'items' => $this->warningItems($noThumbnail, 'article'),
            ],
            [
                'label' => __('公開中・予約公開なのに短文(概要)が未入力の固定ページ'),
                'count' => $noShortSentences->count(),
                'url' => null,
                'items' => $this->warningItems($noShortSentences, 'single_page'),
            ],
            [
                'label' => __('承認待ちの記事'),
                'count' => Article::query()->where('approval', ArticleApprovalStatus::Pending)->count(),
                'url' => route('admin.articles.index', ['approval' => ArticleApprovalStatus::Pending->value]),
                'items' => collect(),
            ],
            [
                'label' => __('承認待ちのギャラリー画像'),
                'count' => GalleryImage::query()->where('approval', ArticleApprovalStatus::Pending)->count(),
                'url' => route('admin.gallery-images.index', ['approval' => ArticleApprovalStatus::Pending->value]),
                'items' => collect(),
            ],
        ];

        return array_values(array_filter($warnings, fn (array $warning) => $warning['count'] > 0));
    }

    /**
     * 最近の操作ログを新しい順に返す。操作ログを見られる管理者(スーパー管理者)には全員の操作を、ほかの管理者には自分の操作だけを返す。
     *
     * @return Collection<int, AuditLog>
     */
    public function recentAuditLogs(Administrator $administrator): Collection
    {
        return AuditLog::query()
            ->unless($administrator->can('view-audit-logs'), fn (Builder $query) => $query
                ->where('actor_type', 'administrator')
                ->where('actor_id', $administrator->id))
            ->newest()
            ->limit(self::RECENT_LIMIT)
            ->get();
    }

    /**
     * 予約公開の記事(公開ステータスが「公開」で、公開開始日時が未来)のクエリ。
     *
     * @return Builder<Article>
     */
    private function scheduledArticles(): Builder
    {
        return Article::query()->where('approval', ArticleApprovalStatus::Published)->upcoming();
    }

    /**
     * 対象ごとに、操作ログの最新の記録の操作者名を返す(キーは「対象の種類:id」)。
     *
     * @param  array<string, list<int>>  $idsByType
     * @return Collection<string, string|null>
     */
    private function latestActorNames(array $idsByType): Collection
    {
        $idsByType = array_filter($idsByType);

        if ($idsByType === []) {
            return collect();
        }

        $latestIds = AuditLog::query()
            ->selectRaw('max(id) as id')
            ->where(function (Builder $query) use ($idsByType) {
                foreach ($idsByType as $type => $ids) {
                    $query->orWhere(fn (Builder $query) => $query->where('subject_type', $type)->whereIn('subject_id', $ids));
                }
            })
            ->groupBy('subject_type', 'subject_id')
            ->pluck('id');

        return AuditLog::query()
            ->whereKey($latestIds)
            ->get(['subject_type', 'subject_id', 'actor_name'])
            ->mapWithKeys(fn (AuditLog $log) => ["{$log->subject_type}:{$log->subject_id}" => $log->actor_name]);
    }

    /**
     * 最近編集したコンテンツの 1 行分。
     *
     * @param  Collection<string, string|null>  $updaters
     * @return array{type: string, title: string, status: string, status_color: string, updated_by: string|null, updated_at: CarbonInterface|null, edit_url: string}
     */
    private function contentRow(Article|SinglePage $content, string $type, Collection $updaters): array
    {
        [$status, $color] = $this->status($content);

        return [
            'type' => $this->typeLabel($type),
            'title' => $content->title,
            'status' => $status,
            'status_color' => $color,
            'updated_by' => $updaters->get("{$type}:{$content->id}"),
            'updated_at' => $content->updated_at,
            'edit_url' => $this->editUrl($content),
        ];
    }

    /**
     * 予約公開の一覧の 1 行分。
     *
     * @return array{type: string, title: string, publish_at: CarbonInterface, edit_url: string}
     */
    private function scheduledRow(Article|SinglePage $content, string $type): array
    {
        return [
            'type' => $this->typeLabel($type),
            'title' => $content->title,
            'publish_at' => $content->publication_start_datetime,
            'edit_url' => $this->editUrl($content),
        ];
    }

    /**
     * コンテンツの注意事項に並べる、編集へのリンク(更新日時の新しい順に WARNING_ITEM_LIMIT 件)。
     *
     * @return Collection<int, array{title: string, edit_url: string}>
     */
    private function warningItems(Builder $query, string $type): Collection
    {
        return (clone $query)->latest('updated_at')->latest('id')->limit(self::WARNING_ITEM_LIMIT)->get()
            ->map(fn (Article|SinglePage $content) => [
                'title' => $content->title,
                'edit_url' => $this->editUrl($content),
            ]);
    }

    /**
     * 現在の状態の表示名とバッジの色。記事の「公開」は、公開期間によって公開中・予約公開・公開終了に分ける。
     *
     * @return array{0: string, 1: string}
     */
    private function status(Article|SinglePage $content): array
    {
        if ($content instanceof Article && $content->approval !== ArticleApprovalStatus::Published) {
            return $content->approval === ArticleApprovalStatus::Pending
                ? [$content->approval->label(), 'warning']
                : [$content->approval->label(), 'secondary'];
        }

        $start = $content->publication_start_datetime;
        $end = $content->publication_end_datetime;

        return match (true) {
            $start === null => [__('非公開'), 'secondary'],
            $start->isFuture() => [__('予約公開'), 'info'],
            $end !== null && $end->lte(now()) => [__('公開終了'), 'secondary'],
            default => [__('公開中'), 'success'],
        };
    }

    private function typeLabel(string $type): string
    {
        return AuditLog::labelForSubjectType($type);
    }

    private function editUrl(Model $content): string
    {
        return $content instanceof Article
            ? route('admin.articles.edit', $content)
            : route('admin.single-pages.edit', $content);
    }
}
