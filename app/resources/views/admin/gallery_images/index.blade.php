@extends('layouts.admin')

@section('title', __('ギャラリー一覧'))

@section('content')
    <div class="mb-4 d-flex align-items-center justify-content-between">
        <h1 class="h5 mb-0">{{ __('ギャラリー一覧') }}</h1>

        <div class="d-flex align-items-center gap-2">
            @if ($galleryImages->isNotEmpty())
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

    @if ($galleryImages->isNotEmpty())
        <form id="gallery-image-reorder-form" method="POST" action="{{ route('admin.gallery-images.reorder') }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="offset" value="{{ $galleryImages->firstItem() - 1 }}">
            <input type="hidden" name="page" value="{{ $galleryImages->currentPage() }}">
        </form>
    @endif

    <div class="card">
        <table class="table table-hover mb-0 align-middle" id="gallery-image-reorder-rows">
            <thead>
                <tr>
                    <th></th>
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
                        <td class="single-page-reorder-handle-cell">
                            <span class="single-page-detail-handle" data-role="drag-handle" title="{{ __('ドラッグして並び替え') }}">
                                <i class="bi bi-grip-vertical"></i>
                            </span>
                            <input type="hidden" form="gallery-image-reorder-form" name="order[]" value="{{ $galleryImage->id }}">
                        </td>
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
                    <x-admin.empty-row colspan="6">{{ __('ギャラリー画像が登録されていません。') }}</x-admin.empty-row>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $galleryImages->links() }}
    </div>
@endsection
