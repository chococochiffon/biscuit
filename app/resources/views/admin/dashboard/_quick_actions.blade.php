{{-- クイック操作(よく使う画面へのショートカット) --}}
<div class="card h-100">
    <div class="card-header bg-transparent fw-semibold">{{ __('クイック操作') }}</div>
    <div class="list-group list-group-flush">
        @foreach ([
            ['route' => 'admin.articles.create', 'icon' => 'bi-pencil-square', 'label' => __('記事を書く')],
            ['route' => 'admin.single-pages.create', 'icon' => 'bi-file-earmark-plus', 'label' => __('固定ページを作成')],
            ['route' => 'admin.gallery-images.create', 'icon' => 'bi-image', 'label' => __('ギャラリー画像を追加')],
            ['route' => 'admin.users.invite', 'icon' => 'bi-person-plus', 'label' => __('ユーザーを招待')],
            ['route' => 'admin.layouts.edit', 'icon' => 'bi-layout-text-window', 'label' => __('レイアウト管理')],
        ] as $action)
            <a href="{{ route($action['route']) }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                <i class="bi {{ $action['icon'] }} text-primary"></i>{{ $action['label'] }}
            </a>
        @endforeach
    </div>
</div>
