@extends('layouts.admin')

@section('title', __('ユーザー詳細'))

@section('content')
    <div class="mb-4 d-flex align-items-center justify-content-between mx-auto" style="max-width: 32rem;">
        <h1 class="h5 mb-0">{{ __('ユーザー詳細') }}</h1>
        <a href="{{ route('admin.users.edit', $user) }}" class="link-primary">{{ __('編集する') }}</a>
    </div>

    <div class="card mx-auto" style="max-width: 32rem;">
        <dl class="row mb-0 p-3 detail-list">
            <x-admin.detail-row :label="__('名前')">{{ $user->name }}</x-admin.detail-row>

            <x-admin.detail-row :label="__('メールアドレス')">{{ $user->email }}</x-admin.detail-row>

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

                <x-admin.detail-row :label="__('表示設定')">{{ $user->detail->view_flag ? __('表示') : __('非表示') }}</x-admin.detail-row>

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
