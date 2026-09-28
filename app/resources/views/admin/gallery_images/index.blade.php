@extends('layouts.admin')

@section('title', __('ギャラリー一覧'))

@section('content')
    <div class="mb-4 d-flex align-items-center justify-content-between">
        <h1 class="h5 mb-0">{{ __('ギャラリー一覧') }}</h1>

        <div class="d-flex align-items-center gap-2">
            @if ($canReorder && $galleryImages->isNotEmpty())
                <button type="submit" form="gallery-image-reorder-form" class="btn btn-outline-secondary btn-sm">
                    {{ __('並び替えを保存') }}
                </button>
            @elseif (! $canReorder)
                <a
                    href="{{ route('admin.gallery-images.index', ['sort' => 'sort_order']) }}"
                    class="btn btn-outline-secondary btn-sm"
                    title="{{ __('検索条件をクリアして表示順で並べ、ドラッグで並び替えられるようにします。') }}"
                >
                    {{ __('表示順で並び替え') }}
                </a>
            @endif

            <button
                type="button"
                class="btn btn-outline-secondary btn-sm"
                data-bs-toggle="modal"
                data-bs-target="#gallery-category-manager-modal"
            >
                {{ __('分類管理') }}
            </button>

            <a href="{{ route('admin.gallery-images.create') }}" class="btn btn-primary">
                {{ __('新規登録') }}
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.gallery-images.index') }}" class="card mb-3 admin-search-card">
        <input type="hidden" name="sort" value="{{ $sort }}">

        @include('admin.partials._search_toggle', ['target' => 'gallery-image-search-body', 'isSearching' => $isSearching])

        <div id="gallery-image-search-body" @class(['collapse', 'show' => $isSearching])>
            <div class="d-flex flex-wrap flex-xl-nowrap gap-3 align-items-end pt-3">
                <div>
                    <label for="search-category" class="form-label small">{{ __('分類') }}</label>
                    <select
                        id="search-category"
                        name="category"
                        class="form-select form-select-sm form-select-auto"
                        data-role="gallery-category-select"
                        data-uncategorized-value="{{ \App\Http\Controllers\GalleryImageController::UNCATEGORIZED }}"
                    >
                        <option value="">{{ __('すべて') }}</option>
                        @foreach ($categories as $categoryOption)
                            <option value="{{ $categoryOption->id }}" @selected($category === (string) $categoryOption->id)>{{ $categoryOption->name }}</option>
                        @endforeach
                        <option value="{{ \App\Http\Controllers\GalleryImageController::UNCATEGORIZED }}" @selected($category === \App\Http\Controllers\GalleryImageController::UNCATEGORIZED)>{{ __('未分類') }}</option>
                    </select>
                </div>

                <div class="d-flex gap-2 text-nowrap">
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('検索') }}</button>
                    <a href="{{ route('admin.gallery-images.index', ['sort' => $sort]) }}" class="btn btn-sm btn-outline-secondary">{{ __('クリア') }}</a>
                </div>
            </div>
        </div>
    </form>

    @if ($canReorder && $galleryImages->isNotEmpty())
        <form id="gallery-image-reorder-form" method="POST" action="{{ route('admin.gallery-images.reorder') }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="offset" value="{{ $galleryImages->firstItem() - 1 }}">
            <input type="hidden" name="page" value="{{ $galleryImages->currentPage() }}">
        </form>
    @endif

    <div class="card">
        <table class="table table-hover mb-0 align-middle" @if ($canReorder) id="gallery-image-reorder-rows" @endif>
            <thead>
                <tr>
                    @if ($canReorder)
                        <th></th>
                    @endif
                    <th>{{ __('画像') }}</th>
                    @include('admin.partials._sortable_th', ['label' => __('名前'), 'field' => 'name', 'defaultDirection' => 'asc'])
                    @include('admin.partials._sortable_th', ['label' => __('分類'), 'field' => 'category', 'defaultDirection' => 'asc'])
                    <th>{{ __('コメント') }}</th>
                    @include('admin.partials._sortable_th', ['label' => __('更新日時'), 'field' => 'updated_at', 'defaultDirection' => 'desc'])
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($galleryImages as $galleryImage)
                    <tr data-role="gallery-image-row">
                        @if ($canReorder)
                            <td class="single-page-reorder-handle-cell">
                                <span class="single-page-detail-handle" data-role="drag-handle" title="{{ __('ドラッグして並び替え') }}">
                                    <i class="bi bi-grip-vertical"></i>
                                </span>
                                <input type="hidden" form="gallery-image-reorder-form" name="order[]" value="{{ $galleryImage->id }}">
                            </td>
                        @endif
                        <td>
                            <img
                                src="{{ $galleryImage->image_url }}"
                                alt="{{ $galleryImage->name }}"
                                class="img-thumbnail"
                                style="width: 64px; height: 64px; object-fit: cover;"
                            >
                        </td>
                        <td>{{ $galleryImage->name }}</td>
                        <td>{{ $galleryImage->category?->name ?? __('未分類') }}</td>
                        <td>{{ $galleryImage->comment }}</td>
                        <td class="text-nowrap">{{ $galleryImage->updated_at?->format('Y/m/d H:i') }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.gallery-images.edit', $galleryImage) }}" class="btn btn-sm btn-outline-secondary">{{ __('編集') }}</a>

                            <x-admin.delete-button :action="route('admin.gallery-images.destroy', $galleryImage)" />
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-row :colspan="$canReorder ? 7 : 6">{{ __('該当するギャラリー画像がありません。') }}</x-admin.empty-row>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $galleryImages->links() }}
    </div>

    {{-- 分類を変更したら、一覧の分類名を最新にするため閉じたときに再読み込みする --}}
    @include('admin.gallery_categories._manager_modal', ['reloadOnChange' => true])
@endsection
