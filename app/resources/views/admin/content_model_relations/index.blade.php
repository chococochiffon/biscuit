@extends('layouts.admin')

@section('title', __('データ種別紐付け一覧'))

@section('content')
    <div class="mb-4 d-flex align-items-center justify-content-between">
        <h1 class="h5 mb-0">{{ __('データ種別紐付け一覧') }}</h1>

        <a href="{{ route('admin.content-model-relations.create') }}" class="btn btn-primary">
            {{ __('新規登録') }}
        </a>
    </div>

    <div class="card">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>{{ __('コンテンツ種別') }}</th>
                    <th>{{ __('モデル名') }}</th>
                    <th>{{ __('テーブル名') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($contentModelRelations as $contentModelRelation)
                    <tr>
                        <td>
                            <a href="{{ route('admin.content-model-relations.show', $contentModelRelation) }}">
                                {{ $contentModelRelation->content_type->label() }}
                            </a>
                        </td>
                        <td>{{ $contentModelRelation->model_name }}</td>
                        <td>{{ $contentModelRelation->table_name }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.content-model-relations.edit', $contentModelRelation) }}" class="btn btn-sm btn-outline-secondary">{{ __('編集') }}</a>

                            <form
                                method="POST"
                                action="{{ route('admin.content-model-relations.destroy', $contentModelRelation) }}"
                                class="d-inline"
                                onsubmit="return confirm('{{ __('削除してよろしいですか?') }}');"
                            >
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('削除') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">{{ __('データ種別の紐付けが登録されていません。') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $contentModelRelations->links() }}
    </div>
@endsection
