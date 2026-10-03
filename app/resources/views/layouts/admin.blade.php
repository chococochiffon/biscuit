<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', __('管理画面')) - {{ config('app.name', 'Laravel') }}</title>

        {{ \Illuminate\Support\Facades\Vite::fonts('nunito') }}
        {{-- admin.js で表示する文言(現在の言語に翻訳済み。JS では t('日本語の原文') で参照する) --}}
        <script>
            window.adminTranslations = @json(\App\Support\AdminJsTranslations::translated());
        </script>
        @vite(['resources/css/admin.css', 'resources/js/admin.js'])
    </head>
    <body class="bg-light">
        <div id="admin-wrapper" class="d-flex">
            <aside class="admin-sidebar flex-shrink-0">
                <a href="{{ route('admin.dashboard') }}" class="admin-sidebar-brand">
                    {{ __('システム管理') }}
                </a>

                <hr class="admin-sidebar-divider">

                <ul class="nav flex-column admin-sidebar-nav">
                    <li class="nav-item">
                        <a href="{{ route('admin.dashboard') }}" class="nav-link">
                            <i class="bi bi-speedometer2"></i>{{ __('ダッシュボード') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.articles.index') }}" class="nav-link">
                            <i class="bi bi-file-earmark-text"></i>{{ __('記事一覧') }}
                            @if ($pendingArticleCount > 0)
                                <span class="badge rounded-pill text-bg-warning ms-1" title="{{ __('承認待ちの記事') }}">{{ $pendingArticleCount }}</span>
                            @endif
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.single-pages.index') }}" class="nav-link">
                            <i class="bi bi-file-earmark-richtext"></i>{{ __('固定ページ一覧') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.gallery-images.index') }}" class="nav-link">
                            <i class="bi bi-images"></i>{{ __('ギャラリー一覧') }}
                            @if ($pendingGalleryImageCount > 0)
                                <span class="badge rounded-pill text-bg-warning ms-1" title="{{ __('承認待ちのギャラリー画像') }}">{{ $pendingGalleryImageCount }}</span>
                            @endif
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.question-answers.index') }}" class="nav-link">
                            <i class="bi bi-question-circle"></i>{{ __('Q&A一覧') }}
                        </a>
                    </li>
                </ul>

                <hr class="admin-sidebar-divider">

                <ul class="nav flex-column admin-sidebar-nav">
                    <li class="nav-item">
                        <a href="{{ route('admin.users.index') }}" class="nav-link">
                            <i class="bi bi-people"></i>{{ __('ユーザー一覧') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.index') }}" class="nav-link">
                            <i class="bi bi-person-badge"></i>{{ __('管理者一覧') }}
                        </a>
                    </li>
                </ul>

                <hr class="admin-sidebar-divider">

                <ul class="nav flex-column admin-sidebar-nav">
                    <li class="nav-item">
                        <a
                            href="{{ $currentSiteSetting ? route('admin.site-settings.show', $currentSiteSetting) : route('admin.site-settings.create') }}"
                            class="nav-link"
                        ><i class="bi bi-gear"></i>{{ __('サイト設定') }}</a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.builder.top') }}" class="nav-link">
                            <i class="bi bi-grid-1x2"></i>{{ __('トップページ編集') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.builder-components.index') }}" class="nav-link">
                            <i class="bi bi-puzzle"></i>{{ __('コンポーネント') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.builder-theme.edit') }}" class="nav-link">
                            <i class="bi bi-palette"></i>{{ __('テーマ') }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('admin.layouts.edit') }}" class="nav-link">
                            <i class="bi bi-layout-sidebar-inset"></i>{{ __('レイアウト管理') }}
                        </a>
                    </li>
                </ul>

                {{-- カスタムページ管理: 種類の管理と、登録した種類ごとのページ一覧(スーパー管理者のみ) --}}
                @can('manage-custom-pages')
                <div class="admin-sidebar-heading">{{ __('カスタムページ管理') }}</div>
                <ul class="nav flex-column admin-sidebar-nav">
                    <li class="nav-item">
                        <a href="{{ route('admin.custom-page-types.index') }}" class="nav-link">
                            <i class="bi bi-ui-checks-grid"></i>{{ __('種類の管理') }}
                        </a>
                    </li>
                    @foreach ($sidebarCustomPageTypes as $sidebarCustomPageType)
                        <li class="nav-item">
                            <a href="{{ route('admin.custom-pages.entries.index', $sidebarCustomPageType) }}" class="nav-link">
                                <i class="bi {{ $sidebarCustomPageType->hasDetails() ? 'bi-file-earmark-richtext' : 'bi-file-earmark-text' }}"></i>{{ $sidebarCustomPageType->label }}
                            </a>
                        </li>
                    @endforeach
                </ul>
                @endcan

                <hr class="admin-sidebar-divider">

                <ul class="nav flex-column admin-sidebar-nav">
                    <li class="nav-item">
                        <a href="{{ route('admin.page-views.index') }}" class="nav-link">
                            <i class="bi bi-graph-up"></i>{{ __('アクセス解析') }}
                        </a>
                    </li>
                </ul>

                {{-- 操作ログ(監査ログ。スーパー管理者のみ) --}}
                @can('view-audit-logs')
                <hr class="admin-sidebar-divider">

                <div class="admin-sidebar-heading">{{ __('システム') }}</div>
                <ul class="nav flex-column admin-sidebar-nav">
                    <li class="nav-item">
                        <a href="{{ route('admin.audit-logs.index') }}" class="nav-link">
                            <i class="bi bi-clock-history"></i>{{ __('操作ログ') }}
                        </a>
                    </li>
                </ul>
                @endcan
            </aside>

            <div class="d-flex flex-column flex-grow-1 min-vh-100">
                <nav class="admin-topbar navbar navbar-expand navbar-light bg-white">
                    <div class="container-fluid">
                        <div class="d-flex w-100 align-items-center justify-content-end gap-3">
                            @auth('admin')
                                <div class="small admin-navbar-text">{{ auth('admin')->user()->name }}</div>
                            @endauth

                            <div class="d-flex align-items-center gap-2">
                                @foreach (config('app.available_locales') as $availableLocale)
                                    <a
                                        href="{{ route('locale.update', $availableLocale) }}"
                                        class="small {{ app()->getLocale() === $availableLocale ? 'fw-semibold text-decoration-none text-body' : 'text-secondary' }}"
                                    >
                                        {{ ['ja' => '日本語', 'en' => 'English'][$availableLocale] ?? $availableLocale }}
                                    </a>
                                @endforeach
                            </div>

                            @auth('admin')
                                <form method="POST" action="{{ route('admin.logout') }}">
                                    @csrf
                                    <button type="submit" class="btn btn-link nav-link text-secondary p-0 small">
                                        {{ __('ログアウト') }}
                                    </button>
                                </form>
                            @endauth
                        </div>
                    </div>
                </nav>

                <main class="flex-grow-1 p-4">
                    {{-- Biscuit からの重要・セキュリティのお知らせ(すべての管理者)と、更新できる Biscuit のバージョンのお知らせ(スーパー管理者だけ)。
                         「閉じる」で隠したものは admin.js(update-notice.js)がこのブラウザで出さない --}}
                    @foreach ($urgentAnnouncements as $urgentAnnouncement)
                        <div class="alert alert-{{ $urgentAnnouncement['level'] === 'security' ? 'danger' : 'warning' }} d-flex align-items-center gap-2 py-2" role="status" data-announcement-notice data-dismissible="announcement:{{ $urgentAnnouncement['id'] }}" hidden>
                            <i class="bi bi-megaphone"></i>
                            <div class="flex-grow-1 small">
                                <span class="fw-semibold">{{ $urgentAnnouncement['level'] === 'security' ? __('セキュリティ') : __('重要') }}:</span>
                                {{ $urgentAnnouncement['title'] }}
                                @if ($urgentAnnouncement['url'])
                                    <a href="{{ $urgentAnnouncement['url'] }}" target="_blank" rel="noopener noreferrer">{{ __('詳しく見る') }}</a>
                                @endif
                            </div>
                            <button type="button" class="btn-close" aria-label="{{ __('閉じる') }}" data-dismiss-notice></button>
                        </div>
                    @endforeach
                    @if ($updateNotice)
                        <div class="alert alert-info d-flex align-items-center gap-2 py-2" role="status" data-update-notice data-dismissible="update:{{ $updateNotice['version'] }}" hidden>
                            <i class="bi bi-arrow-up-circle"></i>
                            <div class="flex-grow-1 small">
                                {{ __('Biscuit の新しいバージョン :version が公開されています(今のバージョン: :current)。', ['version' => $updateNotice['version'], 'current' => config('biscuit.version')]) }}
                                <a href="{{ $updateNotice['url'] }}" target="_blank" rel="noopener noreferrer">{{ __('リリースノートを見る') }}</a>
                            </div>
                            <button type="button" class="btn-close" aria-label="{{ __('閉じる') }}" data-dismiss-notice></button>
                        </div>
                    @endif

                    @if (session('status'))
                        <div class="alert alert-success">
                            {{ session('status') }}
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    @yield('content')
                </main>
            </div>
        </div>
    </body>
</html>
