@include('admin.partials._form_errors')

{{-- 左: アカウント(名前・メールアドレス・パスワード) / 右: ユーザー詳細 --}}
<div class="row g-4 mb-3">
    <div class="col-lg-6">
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
    </div>

    <div class="col-lg-6">
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
            $existingUserImageUrl = ($user ?? null)?->detail?->user_image_url;
        @endphp

        <div
            class="mb-3"
            data-role="image-cropper"
            data-output-width="{{ \App\Models\UserDetail::USER_IMAGE_SIZE }}"
            data-output-height="{{ \App\Models\UserDetail::USER_IMAGE_SIZE }}"
        >
            <label class="form-label">{{ __('アイコン画像') }}</label>
            <x-admin.image-dropzone
                id="user_detail_user_image"
                name="user_detail[user_image]"
                :image-url="$existingUserImageUrl"
                :alt="__('アイコン画像')"
                :aria-label="__('アイコン画像を選択')"
                icon
                :removable="false"
            />
            <input type="hidden" name="user_detail[user_image_crop][x]" data-role="image-cropper-x">
            <input type="hidden" name="user_detail[user_image_crop][y]" data-role="image-cropper-y">
            <input type="hidden" name="user_detail[user_image_crop][width]" data-role="image-cropper-width">
            <input type="hidden" name="user_detail[user_image_crop][height]" data-role="image-cropper-height">
            <div class="form-text">{{ __('画像を選ぶと切り抜き画面が開きます(:sizeで保存します)。', ['size' => \App\Models\UserDetail::USER_IMAGE_SIZE.'×'.\App\Models\UserDetail::USER_IMAGE_SIZE]) }}</div>
            <button type="button" class="btn btn-outline-secondary btn-sm mt-2" data-role="image-cropper-edit" style="display: none;">
                <i class="bi bi-crop"></i> {{ __('切り抜きを編集') }}
            </button>
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
            $skillRows = \App\Support\RepeaterRows::build(
                'user_detail.skills',
                ($user ?? null)?->detail?->skills ?? [],
                fn (array $row) => [
                    'name' => $row['name'] ?? null,
                    'level' => $row['level'] ?? null,
                ],
                fn ($skill) => [
                    'name' => $skill->name,
                    'level' => $skill->level,
                ],
            );
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
    </div>
</div>

@include('admin.partials._image_cropper_modal')
