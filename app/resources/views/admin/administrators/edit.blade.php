@extends('layouts.admin')

@section('title', '管理者の編集')

@section('content')
    <h1 class="mb-6 text-lg font-semibold">管理者の編集</h1>

    <form method="POST" action="{{ route('admin.update', $administrator) }}" class="max-w-md space-y-4">
        @csrf
        @method('PUT')

        @include('admin.administrators._form')

        <div class="flex items-center gap-3">
            <button
                type="submit"
                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
            >
                更新する
            </button>
            <a href="{{ route('admin.index') }}" class="text-sm text-gray-600 hover:text-gray-900">キャンセル</a>
        </div>
    </form>
@endsection
