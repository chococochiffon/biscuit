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
        value="{{ old('name', $administrator->name ?? '') }}"
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
        value="{{ old('email', $administrator->email ?? '') }}"
        required
        class="form-control"
    >
</div>

<div class="mb-3">
    <label for="role" class="form-label">{{ __('権限') }}</label>
    <select id="role" name="role" required class="form-select">
        @foreach (\App\Enums\AdministratorRole::cases() as $role)
            <option
                value="{{ $role->value }}"
                @selected(old('role', $administrator->role?->value ?? '') === $role->value)
            >
                {{ $role->label() }}
            </option>
        @endforeach
    </select>
</div>

<div class="mb-3">
    <label for="password" class="form-label">
        {{ __('パスワード') }}
        @isset($administrator)
            <span class="text-muted small">{{ __('(変更する場合のみ入力)') }}</span>
        @endisset
    </label>
    <input
        id="password"
        type="password"
        name="password"
        autocomplete="new-password"
        @unless(isset($administrator)) required @endunless
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
        @unless(isset($administrator)) required @endunless
        class="form-control"
    >
</div>
