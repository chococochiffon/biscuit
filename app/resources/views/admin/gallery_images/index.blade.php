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
            @endif

            <a href="{{ route('admin.gallery-categories.edit') }}" class="btn btn-outline-secondary btn-sm">
                {{ __('分類の管理') }}
            </a>

            <a href="{{ route('admin.gallery-images.create') }}" class="btn btn-primary">
                {{ __('新規登録') }}
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.gallery-images.index') }}" class="card card-body mb-3 d-flex flex-row flex-wrap align-items-end gap-3">
        <div>
            <label for="search-category" class="form-label small">{{ __('分類') }}</label>
            <select id="search-category" name="category" class="form-select form-select-sm form-select-auto">
                <option value="">{{ __('すべて') }}</option>
                @foreach ($categories as $categoryOption)
                    <option value="{{ $categoryOption->id }}" @selected($category === (string) $categoryOption->id)>{{ $categoryOption->name }}</option>
                @endforeach
                <option value="{{ \App\Http\Controllers\GalleryImageController::UNCATEGORIZED }}" @selected($category === \App\Http\Controllers\GalleryImageController::UNCATEGORIZED)>{{ __('未分類') }}</option>
            </select>
        </div>

        <div class="d-flex gap-2 text-nowrap">
            <button type="submit" class="btn btn-sm btn-primary">{{ __('絞り込む') }}</button>
            <a href="{{ route('admin.gallery-images.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('クリア') }}</a>
        </div>

        @unless ($canReorder)
            <div class="form-text m-0">{{ __('絞り込み中は並び替えできません。並び替えるときはクリアしてください。') }}</div>
        @endunless
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
                    <th>{{ __('名前') }}</th>
                    <th>{{ __('分類') }}</th>
                    <th>{{ __('コメント') }}</th>
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
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.gallery-images.edit', $galleryImage) }}" class="btn btn-sm btn-outline-secondary">{{ __('編集') }}</a>

                            <x-admin.delete-button :action="route('admin.gallery-images.destroy', $galleryImage)" />
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-row :colspan="$canReorder ? 6 : 5">{{ __('該当するギャラリー画像がありません。') }}</x-admin.empty-row>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $galleryImages->links() }}
    </div>
@endsection
