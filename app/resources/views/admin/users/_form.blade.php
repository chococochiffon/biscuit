@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="mb-3">
    <label for="name" class="form-label">{{ __('名前') }}</label>
    <input
        id="name"
        type="text"
        name="name"
        value="{{ old('name', $user->name ?? '') }}"
        required
        class="form-control"
    >
</div>

<div class="mb-3">
    <label for="email" class="form-label">{{ __('メールアドレス') }}</label>
    <input
        id="email"
        type="email"
        name="email"
        value="{{ old('email', $user->email ?? '') }}"
        required
        class="form-control"
    >
</div>

<div class="mb-3">
    <label for="password" class="form-label">
        {{ __('パスワード') }}
        @isset($user)
            <span class="text-muted small">{{ __('(変更する場合のみ入力)') }}</span>
        @endisset
    </label>
    <input
        id="password"
        type="password"
        name="password"
        autocomplete="new-password"
        @unless(isset($user)) required @endunless
        class="form-control"
    >
</div>

<div class="mb-3">
    <label for="password_confirmation" class="form-label">{{ __('パスワード(確認)') }}</label>
    <input
        id="password_confirmation"
        type="password"
        name="password_confirmation"
        autocomplete="new-password"
        @unless(isset($user)) required @endunless
        class="form-control"
    >
</div>

<hr class="my-4">

<h2 class="h6 mb-3">{{ __('ユーザー詳細') }}</h2>

<div class="row">
    <div class="col-6 mb-3">
        <label for="user_detail_first_name" class="form-label">{{ __('名') }}</label>
        <input
            id="user_detail_first_name"
            type="text"
            name="user_detail[first_name]"
            value="{{ old('user_detail.first_name', $user->detail->first_name ?? '') }}"
            required
            class="form-control"
        >
    </div>

    <div class="col-6 mb-3">
        <label for="user_detail_family_name" class="form-label">{{ __('姓') }}</label>
        <input
            id="user_detail_family_name"
            type="text"
            name="user_detail[family_name]"
            value="{{ old('user_detail.family_name', $user->detail->family_name ?? '') }}"
            required
            class="form-control"
        >
    </div>
</div>

<div class="mb-3">
    <label for="user_detail_nick_name" class="form-label">{{ __('ニックネーム') }}</label>
    <input
        id="user_detail_nick_name"
        type="text"
        name="user_detail[nick_name]"
        value="{{ old('user_detail.nick_name', $user->detail->nick_name ?? '') }}"
        required
        class="form-control"
    >
</div>

<div class="mb-3">
    <label for="user_detail_birthday" class="form-label">{{ __('生年月日') }}</label>
    <input
        id="user_detail_birthday"
        type="date"
        name="user_detail[birthday]"
        value="{{ old('user_detail.birthday', optional($user->detail->birthday ?? null)->format('Y-m-d')) }}"
        required
        class="form-control"
        style="max-width: 12rem;"
    >
</div>

@php
    $existingUserImageUrl = ($user->detail->user_image ?? null)
        ? Illuminate\Support\Facades\Storage::disk('public')->url($user->detail->user_image)
        : null;
@endphp

<div class="mb-3">
    <label class="form-label">{{ __('ユーザー画像') }}</label>
    <div class="image-dropzone" data-role="image-dropzone" tabindex="0" role="button" aria-label="{{ __('ユーザー画像を選択') }}">
        <input id="user_detail_user_image" type="file" name="user_detail[user_image]" accept="image/*" class="d-none" data-role="image-dropzone-input">

        <div class="image-dropzone-preview" data-role="image-dropzone-preview" @if (! $existingUserImageUrl) style="display: none;" @endif>
            <img src="{{ $existingUserImageUrl }}" alt="{{ __('ユーザー画像') }}" data-role="image-dropzone-image">
            <button type="button" class="btn btn-sm btn-outline-secondary image-dropzone-remove" data-role="image-dropzone-remove" aria-label="{{ __('選択を解除') }}">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="image-dropzone-placeholder" data-role="image-dropzone-placeholder" @if ($existingUserImageUrl) style="display: none;" @endif>
            <i class="bi bi-cloud-arrow-up"></i>
            <span class="small">{{ __('クリックまたはドラッグ&ドロップ') }}</span>
        </div>
    </div>
</div>

<div class="mb-3">
    <label for="user_detail_comment" class="form-label">{{ __('コメント') }}</label>
    <textarea
        id="user_detail_comment"
        name="user_detail[comment]"
        rows="4"
        class="form-control"
    >{{ old('user_detail.comment', $user->detail->comment ?? '') }}</textarea>
</div>

<div class="mb-3 form-check">
    <input
        id="user_detail_view_flag"
        type="checkbox"
        name="user_detail[view_flag]"
        value="1"
        class="form-check-input"
        @checked(old('user_detail.view_flag', $user->detail->view_flag ?? true))
    >
    <label for="user_detail_view_flag" class="form-check-label">{{ __('表示する') }}</label>
</div>

<div class="mb-3">
    <label for="user_detail_name_settings" class="form-label">{{ __('名前の表示設定') }}</label>
    <select id="user_detail_name_settings" name="user_detail[name_settings]" required class="form-select" style="max-width: 16rem;">
        @foreach (\App\Enums\UserDetailNameSetting::cases() as $nameSetting)
            <option
                value="{{ $nameSetting->value }}"
                @selected((int) old('user_detail.name_settings', $user->detail->name_settings->value ?? \App\Enums\UserDetailNameSetting::Hidden->value) === $nameSetting->value)
            >
                {{ $nameSetting->label() }}
            </option>
        @endforeach
    </select>
</div>

@php
    $oldSkills = old('user_detail.skills');

    $skillRows = $oldSkills !== null
        ? collect($oldSkills)->values()->map(fn ($row, $i) => (object) [
            'index' => (string) $i,
            'id' => $row['id'] ?? null,
            'name' => $row['name'] ?? null,
            'level' => $row['level'] ?? null,
            'sortOrder' => $row['sort_order'] ?? $i,
        ])
        : (($user ?? null)?->detail?->skills ?? collect())->values()->map(fn ($skill, $i) => (object) [
            'index' => (string) $i,
            'id' => $skill->id,
            'name' => $skill->name,
            'level' => $skill->level,
            'sortOrder' => $skill->sort_order,
        ]);
@endphp

<div class="mb-3" data-role="repeater">
    <label class="form-label mb-0">{{ __('スキル') }}</label>
    <div class="form-text mb-2">{{ __('公開側のプロフィール(スキルリスト)に、この順で習熟度のバーを並べます。') }}</div>

    <div data-role="repeater-rows" data-next-index="{{ $skillRows->count() }}">
        @foreach ($skillRows as $row)
            @include('admin.users._skill_row', [
                'index' => $row->index,
                'id' => $row->id,
                'name' => $row->name,
                'level' => $row->level,
                'sortOrder' => $row->sortOrder,
            ])
        @endforeach
    </div>

    <button type="button" class="btn btn-outline-secondary btn-sm" data-role="repeater-add">
        {{ __('+ 行を追加') }}
    </button>

    <template data-role="repeater-template">
        @include('admin.users._skill_row', [
            'index' => '__INDEX__',
            'id' => null,
            'name' => null,
            'level' => null,
            'sortOrder' => 0,
        ])
    </template>
</div>
