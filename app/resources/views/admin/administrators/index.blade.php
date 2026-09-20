@extends('layouts.admin')

@section('title', __('管理者一覧'))

@section('content')
    <div class="mb-4 d-flex align-items-center justify-content-between">
        <h1 class="h5 mb-0">{{ __('管理者一覧') }}</h1>

        <a href="{{ route('admin.create') }}" class="btn btn-primary">
            {{ __('新規登録') }}
        </a>
    </div>

    <div class="card">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>{{ __('名前') }}</th>
                    <th>{{ __('メールアドレス') }}</th>
                    <th>{{ __('権限') }}</th>
                    <th>{{ __('最終ログイン') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($administrators as $administrator)
                    <tr>
                        <td>
                            <a href="{{ route('admin.show', $administrator) }}">
                                {{ $administrator->name }}
                            </a>
                        </td>
                        <td>{{ $administrator->email }}</td>
                        <td>{{ $administrator->role->label() }}</td>
                        <td>
                            {{ $administrator->last_login_at?->format('Y-m-d H:i') ?? __('未ログイン') }}
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.edit', $administrator) }}" class="btn btn-sm btn-outline-secondary">{{ __('編集') }}</a>

                            <form
                                method="POST"
                                action="{{ route('admin.destroy', $administrator) }}"
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
                        <td colspan="5" class="text-center text-muted py-4">{{ __('管理者が登録されていません。') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $administrators->links() }}
    </div>
@endsection
