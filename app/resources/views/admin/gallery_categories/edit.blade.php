@extends('layouts.admin')

@section('title', __('ギャラリーの分類'))

@section('content')
    <h1 class="h5 mb-4 mx-auto" style="max-width: 40rem;">{{ __('ギャラリーの分類') }}</h1>

    <form method="POST" action="{{ route('admin.gallery-categories.update') }}" class="mx-auto" style="max-width: 40rem;">
        @csrf
        @method('PUT')

        @include('admin.partials._form_errors')

        @php
            $categoryRows = \App\Support\RepeaterRows::build(
                'categories',
                $categories,
                fn (array $row) => ['name' => $row['name'] ?? null],
                fn ($category) => ['name' => $category->name],
            );
        @endphp

        <div class="mb-3" data-role="repeater">
            <div class="form-text mb-2">
                {{ __('公開側のギャラリーページに、この順で分類の切り替えを並べます。分類を削除すると、その分類の画像は未分類になります。') }}
            </div>

            <div data-role="repeater-rows" data-next-index="{{ $categoryRows->count() }}">
                @foreach ($categoryRows as $row)
                    @include('admin.gallery_categories._category_row', [
                        'index' => $row->index,
                        'id' => $row->id,
                        'name' => $row->name,
                        'sortOrder' => $row->sortOrder,
                    ])
                @endforeach
            </div>

            <button type="button" class="btn btn-outline-secondary btn-sm" data-role="repeater-add">
                {{ __('+ 行を追加') }}
            </button>

            <template data-role="repeater-template">
                @include('admin.gallery_categories._category_row', [
                    'index' => '__INDEX__',
                    'id' => null,
                    'name' => null,
                    'sortOrder' => 0,
                ])
            </template>
        </div>

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">
                {{ __('保存する') }}
            </button>
            <a href="{{ route('admin.gallery-images.index') }}" class="text-secondary">{{ __('ギャラリー一覧へ戻る') }}</a>
        </div>
    </form>
@endsection
