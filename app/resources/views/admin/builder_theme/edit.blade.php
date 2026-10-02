@extends('layouts.admin')

@section('title', __('テーマ'))

@section('content')
    <div class="mx-auto" style="max-width: 40rem;">
        <h1 class="h5 mb-2">{{ __('テーマ') }}</h1>
        <p class="text-secondary small mb-4">
            {{ __('ページビルダーのブロックで使う色とフォントです。ブロックのスタイルで「テーマの色」を選ぶと、ここで変えた色が全ページに反映されます。ボタンのブロックの「メイン」「サブ」の色もテーマに従います。') }}
        </p>

        <form method="POST" action="{{ route('admin.builder-theme.update') }}">
            @csrf
            @method('PUT')

            @include('admin.partials._form_errors')

            <h2 class="h6 mb-3">{{ __('色') }}</h2>
            <div class="row g-3 mb-4">
                @foreach ($colors as $name => $color)
                    @php($value = old("colors.{$name}", $theme->colorValues()[$name]))
                    <div class="col-sm-6">
                        <label for="color-{{ $name }}" class="form-label">{{ __($color['label']) }}</label>
                        <input
                            id="color-{{ $name }}"
                            type="color"
                            name="colors[{{ $name }}]"
                            value="{{ $value }}"
                            class="form-control form-control-color @error("colors.{$name}") is-invalid @enderror"
                        >
                        <div class="form-text">{{ __('既定: :value', ['value' => $color['default']]) }}</div>
                    </div>
                @endforeach
            </div>

            <h2 class="h6 mb-3">{{ __('フォント') }}</h2>
            @foreach (['heading_font' => __('見出しのフォント'), 'body_font' => __('本文のフォント')] as $field => $label)
                <div class="mb-3">
                    <label for="{{ $field }}" class="form-label">{{ $label }}</label>
                    <select id="{{ $field }}" name="{{ $field }}" class="form-select form-select-auto @error($field) is-invalid @enderror">
                        <option value="">{{ __('サイトの既定') }}</option>
                        @foreach ($fonts as $key => $font)
                            <option value="{{ $key }}" @selected(old($field, $theme->{$field}) === $key)>{{ $font['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
            <p class="form-text mb-4">{{ __('フォントは公開側で Google Fonts から読み込みます。') }}</p>

            <button type="submit" class="btn btn-primary">{{ __('保存する') }}</button>
        </form>
    </div>
@endsection
