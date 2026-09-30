{{-- コンテンツの状況(記事・固定ページの状態ごとの件数。$counts は DashboardService::contentCounts()) --}}
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card h-100" data-content-counts="article">
            <x-admin.count-summary
                class="card-body"
                :label="__('記事')"
                :url="route('admin.articles.index')"
                :counts="$counts['article']"
                :items="['published' => __('公開中'), 'scheduled' => __('予約公開'), 'draft' => __('下書き'), 'unpublished' => __('非公開')]"
            />
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100" data-content-counts="single_page">
            <x-admin.count-summary
                class="card-body"
                :label="__('固定ページ')"
                :url="route('admin.single-pages.index')"
                :counts="$counts['single_page']"
                :items="['published' => __('公開中'), 'scheduled' => __('予約公開'), 'unpublished' => __('非公開')]"
            />
        </div>
    </div>
</div>
