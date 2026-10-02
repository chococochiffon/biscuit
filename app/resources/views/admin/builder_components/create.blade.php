@extends('layouts.admin')

@section('title', __('コンポーネントの登録'))

@section('content')
    <h1 class="h5 mb-4 mx-auto" style="max-width: 32rem;">{{ __('コンポーネントの登録') }}</h1>

    <form method="POST" action="{{ route('admin.builder-components.store') }}" class="mx-auto" style="max-width: 32rem;">
        @csrf

        @include('admin.builder_components._form')

        <fieldset class="mb-3">
            <legend class="form-label fs-6">{{ __('種類') }}</legend>
            @foreach (\App\Enums\BuilderComponentKind::cases() as $kind)
                <div class="form-check">
                    <input
                        id="kind-{{ $kind->value }}"
                        type="radio"
                        name="kind"
                        value="{{ $kind->value }}"
                        class="form-check-input @error('kind') is-invalid @enderror"
                        @checked(old('kind', 'global') === $kind->value)
                    >
                    <label for="kind-{{ $kind->value }}" class="form-check-label">{{ $kind->label() }}</label>
                    <div class="form-text mt-0">
                        {{ $kind === \App\Enums\BuilderComponentKind::Global
                            ? __('どのページでも同じ中身のパーツ(ヘッダー・お問い合わせへの案内など)。ページの一番外側に置きます。')
                            : __('使うたびに一部の項目(見出しの文字・画像など)を差し替えられる部品。ページのセクションなどの中に、パレットから置きます。') }}
                    </div>
                </div>
            @endforeach
            <div class="form-text">{{ __('種類はあとから変えられません。') }}</div>
        </fieldset>

        <p class="small text-secondary">{{ __('登録すると、ページビルダーが開きます。中身はページビルダーで組み立てます。') }}</p>

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">
                {{ __('登録する') }}
            </button>
            <a href="{{ route('admin.builder-components.index') }}" class="text-secondary">{{ __('キャンセル') }}</a>
        </div>
    </form>
@endsection
