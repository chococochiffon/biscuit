@extends('layouts.admin')

@section('title', '管理者詳細')

@section('content')
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-lg font-semibold">管理者詳細</h1>
        <a href="{{ route('admin.edit', $administrator) }}" class="text-sm text-indigo-600 hover:underline">編集する</a>
    </div>

    <dl class="max-w-md divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white text-sm">
        <div class="grid grid-cols-3 gap-4 px-4 py-3">
            <dt class="text-gray-500">名前</dt>
            <dd class="col-span-2">{{ $administrator->name }}</dd>
        </div>
        <div class="grid grid-cols-3 gap-4 px-4 py-3">
            <dt class="text-gray-500">メールアドレス</dt>
            <dd class="col-span-2">{{ $administrator->email }}</dd>
        </div>
        <div class="grid grid-cols-3 gap-4 px-4 py-3">
            <dt class="text-gray-500">権限</dt>
            <dd class="col-span-2">{{ $administrator->role->value }}</dd>
        </div>
        <div class="grid grid-cols-3 gap-4 px-4 py-3">
            <dt class="text-gray-500">最終ログイン</dt>
            <dd class="col-span-2">{{ $administrator->last_login_at?->format('Y-m-d H:i') ?? '未ログイン' }}</dd>
        </div>
    </dl>

    <div class="mt-6">
        <a href="{{ route('admin.index') }}" class="text-sm text-gray-600 hover:text-gray-900">一覧へ戻る</a>
    </div>
@endsection
