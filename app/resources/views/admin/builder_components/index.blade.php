@extends('layouts.admin')

@section('title', __('グローバルコンポーネント一覧'))

@section('content')
    <div class="mb-2 d-flex align-items-center justify-content-between">
        <h1 class="h5 mb-0">{{ __('グローバルコンポーネント一覧') }}</h1>

        <a href="{{ route('admin.builder-components.create') }}" class="btn btn-primary">
            {{ __('新規登録') }}
        </a>
    </div>
    <p class="small text-secondary mb-4">
        {{ __('ヘッダー・お問い合わせへの案内など、複数のページで使う共通のパーツです。ページビルダーの「グローバルコンポーネント」のブロックで選んで置き、公開すると使っているページすべてに反映されます。') }}
    </p>

    <div class="card">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>{{ __('名前') }}</th>
                    <th>{{ __('説明') }}</th>
                    <th>{{ __('状態') }}</th>
                    <th>{{ __('使っているページ') }}</th>
                    <th>{{ __('更新日時') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($components as $builderComponent)
                    <tr>
                        <td><a href="{{ route('admin.builder.components', $builderComponent) }}">{{ $builderComponent->name }}</a></td>
                        <td class="small text-secondary">{{ $builderComponent->description }}</td>
                        <td>
                            @if (! $builderComponent->isPublished())
                                <span class="badge text-bg-secondary">{{ __('未公開') }}</span>
                            @elseif ($builderComponent->hasUnpublishedChanges())
                                <span class="badge text-bg-warning">{{ __('公開中(未公開の変更あり)') }}</span>
                            @else
                                <span class="badge text-bg-success">{{ __('公開中') }}</span>
                            @endif
                        </td>
                        <td>{{ __(':count ページ', ['count' => $usages[$builderComponent->id] ?? 0]) }}</td>
                        <td>{{ $builderComponent->updated_at?->format('Y/m/d H:i') }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.builder.components', $builderComponent) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('ページビルダーで編集') }}">
                                <i class="bi bi-grid-1x2"></i><span class="visually-hidden">{{ __('ページビルダーで編集') }}</span>
                            </a>
                            <a href="{{ route('admin.builder-components.edit', $builderComponent) }}" class="btn btn-sm btn-outline-secondary">{{ __('編集') }}</a>

                            <x-admin.delete-button :action="route('admin.builder-components.destroy', $builderComponent)" />
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-row colspan="6">{{ __('グローバルコンポーネントが登録されていません。') }}</x-admin.empty-row>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $components->links() }}
    </div>
@endsection
