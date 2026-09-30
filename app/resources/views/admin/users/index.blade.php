@extends('layouts.admin')

@section('title', __('ユーザー一覧'))

@section('content')
    <div class="mb-4 d-flex align-items-center justify-content-between">
        <h1 class="h5 mb-0">{{ __('ユーザー一覧') }}</h1>

        <div class="d-flex gap-2">
            <a href="{{ route('admin.users.invite') }}" class="btn btn-outline-primary">
                {{ __('招待') }}
            </a>
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
                {{ __('新規登録') }}
            </a>
        </div>
    </div>

    <div class="card">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>{{ __('アイコン画像') }}</th>
                    <th>{{ __('名') }}</th>
                    <th>{{ __('姓') }}</th>
                    <th>{{ __('メールアドレス') }}</th>
                    <th>{{ __('トップへ表示する') }}</th>
                    <th>{{ __('名前の表示設定') }}</th>
                    <th>{{ __('状態') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>
                            @if ($user->detail?->user_image)
                                <img
                                    src="{{ $user->detail->user_image_url }}"
                                    alt="{{ __('アイコン画像') }}"
                                    class="rounded-circle"
                                    style="width: 40px; height: 40px; object-fit: cover;"
                                >
                            @else
                                <i class="bi bi-person-circle text-body-secondary" style="font-size: 40px; line-height: 1;"></i>
                            @endif
                        </td>
                        <td>
                            {{-- ユーザー詳細が未登録のユーザーは、名の代わりにユーザー名で詳細画面へのリンクを出す --}}
                            <a href="{{ route('admin.users.show', $user) }}">
                                {{ $user->detail?->first_name ?? $user->name }}
                            </a>
                        </td>
                        <td>{{ $user->detail?->family_name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>
                            @if ($user->detail)
                                {{ $user->detail->view_flag ? __('表示する') : __('表示しない') }}
                            @endif
                        </td>
                        <td>{{ $user->detail?->name_settings->label() }}</td>
                        <td class="text-nowrap">
                            {{-- 招待されてまだプロフィールを登録していないユーザーは、招待中か期限切れかを出す --}}
                            @if ($user->active_flag)
                                <span class="badge text-bg-success">{{ __('有効') }}</span>
                            @elseif ($user->latestInvitation?->isExpired() ?? true)
                                <span class="badge text-bg-danger">{{ __('招待の期限切れ') }}</span>
                            @else
                                <span class="badge text-bg-warning">{{ __('招待中') }}</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            @unless ($user->active_flag)
                                <form method="POST" action="{{ route('admin.users.invitation.resend', $user) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-primary">{{ __('招待を再送') }}</button>
                                </form>
                            @endunless

                            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-secondary">{{ __('編集') }}</a>

                            <x-admin.delete-button :action="route('admin.users.destroy', $user)" />
                        </td>
                    </tr>
                @empty
                    <x-admin.empty-row colspan="8">{{ __('ユーザーが登録されていません。') }}</x-admin.empty-row>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $users->links() }}
    </div>
@endsection
