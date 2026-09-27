@extends('layouts.admin')

@section('title', __('タグ一覧'))

@section('content')
    <div class="mb-4 d-flex align-items-center justify-content-between">
        <h1 class="h5 mb-0">{{ __('タグ一覧') }}</h1>

        <a href="{{ route('admin.tags.create') }}" class="btn btn-primary">
            {{ __('新規登録') }}
        </a>
    </div>

    <div class="card">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>{{ __('タグ名') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tags as $tag)
                    <tr>
                        <td>
                            <a href="{{ route('admin.tags.show', $tag) }}">
                                {{ $tag->tag_name }}
                            </a>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.tags.edit', $tag) }}" class="btn btn-sm btn-outline-secondary">{{ __('編集') }}</a>

                            <x-admin.delete-button :action="route('admin.tags.destroy', $tag)" />
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-row colspan="2">{{ __('タグが登録されていません。') }}</x-admin.empty-row>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $tags->links() }}
    </div>
@endsection
