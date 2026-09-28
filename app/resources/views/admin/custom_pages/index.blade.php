@extends('layouts.admin')

@section('title', __(':label一覧', ['label' => $customPageType->label]))

@section('content')
    <div class="mb-4 d-flex align-items-center justify-content-between">
        <h1 class="h5 mb-0">{{ __(':label一覧', ['label' => $customPageType->label]) }}</h1>

        <div class="d-flex align-items-center gap-2">
            <a href="{{ route('admin.custom-page-types.edit', $customPageType) }}" class="btn btn-outline-secondary btn-sm">
                {{ __('カスタムフォームの設定') }}
            </a>
            <a href="{{ route('admin.custom-pages.entries.create', $customPageType) }}" class="btn btn-primary">
                {{ __('新規登録') }}
            </a>
        </div>
    </div>

    <div class="card">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>{{ __('タイトル') }}</th>
                    @if ($customPageType->hasDetails())
                        <th>{{ __('概要') }}</th>
                    @else
                        <th>{{ __('ステータス') }}</th>
                    @endif
                    <th>{{ __('公開開始') }}</th>
                    <th>{{ __('公開終了') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td>{{ $entry->title }}</td>
                        @if ($customPageType->hasDetails())
                            <td>{{ $entry->short_sentences }}</td>
                        @else
                            <td>{{ $entry->approval->label() }}</td>
                        @endif
                        <td class="text-nowrap">{{ $entry->publication_start_datetime?->format('Y/m/d H:i') }}</td>
                        <td class="text-nowrap">{{ $entry->publication_end_datetime?->format('Y/m/d H:i') ?? __('未設定') }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.custom-pages.entries.edit', [$customPageType, $entry->id]) }}" class="btn btn-sm btn-outline-secondary">{{ __('編集') }}</a>

                            <x-admin.delete-button :action="route('admin.custom-pages.entries.destroy', [$customPageType, $entry->id])" />
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-row colspan="5">{{ __(':labelが登録されていません。', ['label' => $customPageType->label]) }}</x-admin.empty-row>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $entries->links() }}
    </div>
@endsection
