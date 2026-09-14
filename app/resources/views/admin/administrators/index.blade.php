@extends('layouts.admin')

@section('title', '管理者一覧')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-lg font-semibold">管理者一覧</h1>

        <a
            href="{{ route('admin.create') }}"
            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
        >
            新規登録
        </a>
    </div>

    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">名前</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">メールアドレス</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">権限</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">最終ログイン</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($administrators as $administrator)
                    <tr>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.show', $administrator) }}" class="text-indigo-600 hover:underline">
                                {{ $administrator->name }}
                            </a>
                        </td>
                        <td class="px-4 py-3">{{ $administrator->email }}</td>
                        <td class="px-4 py-3">{{ $administrator->role->value }}</td>
                        <td class="px-4 py-3">
                            {{ $administrator->last_login_at?->format('Y-m-d H:i') ?? '未ログイン' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.edit', $administrator) }}" class="text-gray-600 hover:text-gray-900">編集</a>

                            <form
                                method="POST"
                                action="{{ route('admin.destroy', $administrator) }}"
                                class="inline"
                                onsubmit="return confirm('削除してよろしいですか?');"
                            >
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ml-3 text-red-600 hover:text-red-800">削除</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">管理者が登録されていません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $administrators->links() }}
    </div>
@endsection
