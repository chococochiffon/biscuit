@extends('layouts.admin')

@section('title', __('カスタムページの新規登録'))

@section('content')
    <h1 class="h5 mb-4 mx-auto" style="max-width: 40rem;">{{ __('カスタムページの新規登録') }}</h1>

    <form method="POST" action="{{ route('admin.custom-page-types.store') }}" class="mx-auto" style="max-width: 40rem;">
        @csrf

        @include('admin.partials._form_errors')

        <div class="mb-3">
            <label for="label" class="form-label">{{ __('表示名') }}</label>
            <input id="label" type="text" name="label" value="{{ old('label') }}" maxlength="128" required class="form-control">
            <div class="form-text">{{ __('管理画面のメニューや見出しに表示する名前です(例: レシピ)。') }}</div>
        </div>

        <div class="mb-3">
            <label for="name" class="form-label">{{ __('カスタム名') }}</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" maxlength="30" required class="form-control" pattern="[A-Za-z][A-Za-z0-9_]*">
            <div class="form-text">
                {{ __('テーブル名に使う半角英字の名前です(例: recipe)。単数形にそろえ、本体のテーブルは複数形(例: user_make_recipes)で作ります。登録後は変更できません。') }}
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label d-block">{{ __('型') }}</label>
            @foreach (\App\Enums\CustomPageBaseType::cases() as $baseType)
                <div class="form-check form-check-inline">
                    <input
                        id="base_type_{{ $baseType->value }}"
                        type="radio"
                        name="base_type"
                        value="{{ $baseType->value }}"
                        class="form-check-input"
                        @checked((int) old('base_type', \App\Enums\CustomPageBaseType::Article->value) === $baseType->value)
                    >
                    <label for="base_type_{{ $baseType->value }}" class="form-check-label">{{ $baseType->label() }}</label>
                </div>
            @endforeach
            <div class="form-text">{{ __('固定ページ型は、小見出しと本文の詳細を繰り返し登録できます。登録後は変更できません。') }}</div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">{{ __('登録する') }}</button>
            <a href="{{ route('admin.custom-page-types.index') }}" class="text-secondary">{{ __('キャンセル') }}</a>
        </div>
    </form>
@endsection
