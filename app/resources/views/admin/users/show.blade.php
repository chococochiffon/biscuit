@extends('layouts.admin')

@section('title', __('ユーザー詳細'))

@section('content')
    <div class="mb-4 d-flex align-items-center justify-content-between mx-auto" style="max-width: 32rem;">
        <h1 class="h5 mb-0">{{ __('ユーザー詳細') }}</h1>
        <a href="{{ route('admin.users.edit', $user) }}" class="link-primary">{{ __('編集する') }}</a>
    </div>

    <div class="card mx-auto" style="max-width: 32rem;">
        <dl class="row mb-0 p-3 detail-list">
            <x-admin.detail-row :label="__('アカウント名')">{{ $user->name }}</x-admin.detail-row>

            <x-admin.detail-row :label="__('メールアドレス')">{{ $user->email }}</x-admin.detail-row>

            <x-admin.detail-row :label="__('記事とギャラリーの承認')">{{ $user->skip_approval ? __('承認なしで公開する') : __('承認が必要') }}</x-admin.detail-row>

            <x-admin.detail-row :label="__('二段階認証')">
                <span data-two-factor-method="{{ $user->usesTotp() ? 'totp' : 'email' }}">{{ ($user->usesTotp() ? \App\Enums\TwoFactorMethod::Totp : \App\Enums\TwoFactorMethod::Email)->label() }}</span>
                {{-- 端末をなくしたユーザーの認証アプリを、スーパー管理者が解除してメールに戻す --}}
                @if ($user->usesTotp())
                    @can('reset-two-factor')
                        <form method="POST" action="{{ route('admin.users.two-factor.reset', $user) }}" class="mt-2" onsubmit="return confirm(@js(__('このユーザーの認証アプリを解除し、メールの確認コードに戻しますか?')))" data-two-factor-reset>
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('認証アプリを解除する') }}</button>
                        </form>
                    @endcan
                @endif
            </x-admin.detail-row>

            @if ($user->detail)
                <x-admin.detail-row :label="__('氏名')">{{ $user->detail->family_name }} {{ $user->detail->first_name }}</x-admin.detail-row>

                <x-admin.detail-row :label="__('ニックネーム')">{{ $user->detail->nick_name }}</x-admin.detail-row>

                <x-admin.detail-row :label="__('生年月日')">{{ $user->detail->birthday->format('Y/m/d') }}</x-admin.detail-row>

                <x-admin.detail-row :label="__('アイコン画像')">
                    @if ($user->detail->user_image)
                        <img
                            src="{{ $user->detail->user_image_url }}"
                            alt="{{ __('アイコン画像') }}"
                            class="img-thumbnail"
                            style="width: 120px; height: 120px; object-fit: cover;"
                        >
                    @else
                        {{ __('未設定') }}
                    @endif
                </x-admin.detail-row>

                <x-admin.detail-row :label="__('コメント')" style="white-space: pre-wrap;">{{ $user->detail->comment }}</x-admin.detail-row>

                <x-admin.detail-row :label="__('トップへ表示する')">{{ $user->detail->view_flag ? __('表示する') : __('表示しない') }}</x-admin.detail-row>

                <x-admin.detail-row :label="__('名前の表示設定')">{{ $user->detail->name_settings->label() }}</x-admin.detail-row>

                <x-admin.detail-row :label="__('スキル')">
                    @forelse ($user->detail->skills as $skill)
                        <div class="small">{{ $skill->name }}</div>
                        <div class="progress mb-2" role="progressbar" aria-label="{{ $skill->name }}" aria-valuenow="{{ $skill->level }}" aria-valuemin="0" aria-valuemax="{{ \App\Models\UserSkill::MAX_LEVEL }}" style="height: 6px;">
                            <div class="progress-bar" style="width: {{ $skill->level / \App\Models\UserSkill::MAX_LEVEL * 100 }}%;"></div>
                        </div>
                    @empty
                        <span class="text-muted">{{ __('未登録') }}</span>
                    @endforelse
                </x-admin.detail-row>
            @endif
        </dl>
    </div>

    <div class="mt-4">
        <a href="{{ route('admin.users.index') }}" class="text-secondary">{{ __('一覧へ戻る') }}</a>
    </div>
@endsection
