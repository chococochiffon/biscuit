@extends('layouts.admin')

@section('title', __('カスタムページの種類'))

@section('content')
    <div class="mb-4 d-flex align-items-center justify-content-between">
        <h1 class="h5 mb-0">{{ __('カスタムページの種類') }}</h1>

        @if ($customPageTypes->count() < config('limits.custom_page_types'))
            <a href="{{ route('admin.custom-page-types.create') }}" class="btn btn-primary">
                {{ __('新規登録') }}
            </a>
        @endif
    </div>

    <p class="small text-muted">
        {{ __('カスタムページは:max件まで登録できます。', ['max' => config('limits.custom_page_types')]) }}
    </p>

    <div class="card">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>{{ __('表示名') }}</th>
                    <th>{{ __('カスタム名') }}</th>
                    <th>{{ __('型') }}</th>
                    <th>{{ __('テーブル') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($customPageTypes as $customPageType)
                    <tr>
                        <td>
                            <a href="{{ route('admin.custom-pages.entries.index', $customPageType) }}">{{ $customPageType->label }}</a>
                        </td>
                        <td><code>{{ $customPageType->name }}</code></td>
                        <td>{{ $customPageType->base_type->label() }}</td>
                        <td class="small">
                            @foreach ($customPageType->tableNames() as $tableName)
                                <code class="d-block">{{ $tableName }}</code>
                            @endforeach
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.custom-page-types.edit', $customPageType) }}" class="btn btn-sm btn-outline-secondary">{{ __('編集') }}</a>

                            <x-admin.delete-button :action="route('admin.custom-page-types.destroy', $customPageType)" />
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-row colspan="5">{{ __('カスタムページが登録されていません。') }}</x-admin.empty-row>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
