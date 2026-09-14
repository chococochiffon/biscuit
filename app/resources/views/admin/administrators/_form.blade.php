@if ($errors->any())
    <div class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-800">
        <ul class="list-inside list-disc">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div>
    <label for="name" class="block text-sm font-medium text-gray-700">名前</label>
    <input
        id="name"
        type="text"
        name="name"
        value="{{ old('name', $administrator->name ?? '') }}"
        required
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
    >
</div>

<div>
    <label for="email" class="block text-sm font-medium text-gray-700">メールアドレス</label>
    <input
        id="email"
        type="email"
        name="email"
        value="{{ old('email', $administrator->email ?? '') }}"
        required
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
    >
</div>

<div>
    <label for="role" class="block text-sm font-medium text-gray-700">権限</label>
    <select
        id="role"
        name="role"
        required
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
    >
        @foreach (\App\Enums\AdministratorRole::cases() as $role)
            <option
                value="{{ $role->value }}"
                @selected(old('role', $administrator->role?->value ?? '') === $role->value)
            >
                {{ $role->value }}
            </option>
        @endforeach
    </select>
</div>

<div>
    <label for="password" class="block text-sm font-medium text-gray-700">
        パスワード
        @isset($administrator)
            <span class="text-xs text-gray-400">(変更する場合のみ入力)</span>
        @endisset
    </label>
    <input
        id="password"
        type="password"
        name="password"
        autocomplete="new-password"
        @unless(isset($administrator)) required @endunless
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
    >
</div>

<div>
    <label for="password_confirmation" class="block text-sm font-medium text-gray-700">パスワード(確認)</label>
    <input
        id="password_confirmation"
        type="password"
        name="password_confirmation"
        autocomplete="new-password"
        @unless(isset($administrator)) required @endunless
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
    >
</div>
