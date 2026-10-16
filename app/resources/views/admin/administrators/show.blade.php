@extends('layouts.admin')

@section('title', __('管理者詳細'))

@section('content')
    <div class="mb-4 d-flex align-items-center justify-content-between mx-auto" style="max-width: 28rem;">
        <h1 class="h5 mb-0">{{ __('管理者詳細') }}</h1>
        <a href="{{ route('admin.edit', $administrator) }}" class="link-primary">{{ __('編集する') }}</a>
    </div>

    <div class="card mx-auto" style="max-width: 28rem;">
        <dl class="row mb-0 p-3 detail-list">
            <x-admin.detail-row :label="__('名前')">{{ $administrator->name }}</x-admin.detail-row>

            <x-admin.detail-row :label="__('メールアドレス')">{{ $administrator->email }}</x-admin.detail-row>

            <x-admin.detail-row :label="__('権限')">{{ $administrator->role->label() }}</x-admin.detail-row>

            <x-admin.detail-row :label="__('二段階認証')">
                <span data-two-factor-method="{{ $administrator->usesTotp() ? 'totp' : 'email' }}">{{ ($administrator->usesTotp() ? \App\Enums\TwoFactorMethod::Totp : \App\Enums\TwoFactorMethod::Email)->label() }}</span>
                {{-- 端末をなくした管理者の認証アプリを、スーパー管理者が解除してメールに戻す(自分の設定は「二段階認証」の画面で変える) --}}
                @if ($administrator->usesTotp() && ! $administrator->is(auth('admin')->user()))
                    @can('reset-two-factor')
                        <form method="POST" action="{{ route('admin.two-factor.reset', $administrator) }}" class="mt-2" onsubmit="return confirm(@js(__('この管理者の認証アプリを解除し、メールの確認コードに戻しますか?')))" data-two-factor-reset>
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('認証アプリを解除する') }}</button>
                        </form>
                    @endcan
                @endif
            </x-admin.detail-row>

            <x-admin.detail-row :label="__('最終ログイン')">{{ $administrator->last_login_at?->format('Y/m/d H:i') ?? __('未ログイン') }}</x-admin.detail-row>
        </dl>
    </div>

    <div class="mt-4">
        <a href="{{ \App\Support\AdminListUrl::to('admin.index') }}" class="text-secondary">{{ __('一覧へ戻る') }}</a>
    </div>
@endsection
