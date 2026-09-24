@extends('layouts.admin')

@section('title', __('ユーザー詳細'))

@section('content')
    <div class="mb-4 d-flex align-items-center justify-content-between mx-auto" style="max-width: 32rem;">
        <h1 class="h5 mb-0">{{ __('ユーザー詳細') }}</h1>
        <a href="{{ route('admin.users.edit', $user) }}" class="link-primary">{{ __('編集する') }}</a>
    </div>

    <div class="card mx-auto" style="max-width: 32rem;">
        <dl class="row mb-0 p-3">
            <dt class="col-4 text-muted fw-normal">{{ __('名前') }}</dt>
            <dd class="col-8">{{ $user->name }}</dd>

            <dt class="col-4 text-muted fw-normal">{{ __('メールアドレス') }}</dt>
            <dd class="col-8">{{ $user->email }}</dd>

            @if ($user->detail)
                <dt class="col-4 text-muted fw-normal">{{ __('氏名') }}</dt>
                <dd class="col-8">{{ $user->detail->family_name }} {{ $user->detail->first_name }}</dd>

                <dt class="col-4 text-muted fw-normal">{{ __('ニックネーム') }}</dt>
                <dd class="col-8">{{ $user->detail->nick_name }}</dd>

                <dt class="col-4 text-muted fw-normal">{{ __('生年月日') }}</dt>
                <dd class="col-8">{{ $user->detail->birthday->format('Y/m/d') }}</dd>

                <dt class="col-4 text-muted fw-normal">{{ __('ユーザー画像') }}</dt>
                <dd class="col-8">
                    @if ($user->detail->user_image)
                        <img
                            src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($user->detail->user_image) }}"
                            alt="{{ __('ユーザー画像') }}"
                            class="img-thumbnail"
                            style="width: 120px; height: 120px; object-fit: cover;"
                        >
                    @else
                        {{ __('未設定') }}
                    @endif
                </dd>

                <dt class="col-4 text-muted fw-normal">{{ __('コメント') }}</dt>
                <dd class="col-8" style="white-space: pre-wrap;">{{ $user->detail->comment }}</dd>

                <dt class="col-4 text-muted fw-normal">{{ __('表示設定') }}</dt>
                <dd class="col-8">{{ $user->detail->view_flag ? __('表示') : __('非表示') }}</dd>

                <dt class="col-4 text-muted fw-normal">{{ __('名前の表示設定') }}</dt>
                <dd class="col-8 mb-0">{{ $user->detail->name_settings->label() }}</dd>
            @endif
        </dl>
    </div>

    <div class="mt-4">
        <a href="{{ route('admin.users.index') }}" class="text-secondary">{{ __('一覧へ戻る') }}</a>
    </div>
@endsection
