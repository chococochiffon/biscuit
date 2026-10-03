@extends('installer.layout')

@section('title', __('サイト'))

@section('content')
    <h2 class="h5">{{ __('サイト') }}</h2>
    <p class="text-secondary small">{{ __('サイトの基本の情報を決めます。あとから管理画面のサイト設定で変えられます。') }}</p>

    @include('admin.partials._form_errors')

    <form method="POST" action="{{ route('installer.site.store') }}">
        @csrf

        <div class="mb-3">
            <label for="site_title" class="form-label">{{ __('サイト名') }}</label>
            <input id="site_title" type="text" name="site_title" value="{{ $values['site_title'] }}" required maxlength="255" class="form-control @error('site_title') is-invalid @enderror">
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">{{ __('サイトの説明') }}</label>
            <textarea id="description" name="description" rows="2" maxlength="1000" class="form-control @error('description') is-invalid @enderror">{{ $values['description'] }}</textarea>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-sm-6">
                <label for="locale" class="form-label">{{ __('管理画面の言語') }}</label>
                <select id="locale" name="locale" class="form-select @error('locale') is-invalid @enderror">
                    <option value="ja" @selected($values['locale'] === 'ja')>日本語</option>
                    <option value="en" @selected($values['locale'] === 'en')>English</option>
                </select>
            </div>
            <div class="col-sm-6">
                <label for="timezone" class="form-label">{{ __('タイムゾーン') }}</label>
                <input id="timezone" type="text" name="timezone" value="{{ $values['timezone'] }}" required list="timezones" class="form-control @error('timezone') is-invalid @enderror">
                <datalist id="timezones">
                    @foreach (\DateTimeZone::listIdentifiers() as $timezone)
                        <option value="{{ $timezone }}"></option>
                    @endforeach
                </datalist>
            </div>
        </div>

        <div class="mb-3">
            <label for="front_url" class="form-label">{{ __('公開側の URL') }}</label>
            <input id="front_url" type="url" name="front_url" value="{{ $values['front_url'] }}" required class="form-control @error('front_url') is-invalid @enderror">
            <div class="form-text">{{ __('訪問者が見るサイトの URL(例: https://example.com)。') }}</div>
        </div>

        <div class="mb-4">
            <label for="admin_url" class="form-label">{{ __('管理画面の URL') }}</label>
            <input id="admin_url" type="url" name="admin_url" value="{{ $values['admin_url'] }}" required class="form-control @error('admin_url') is-invalid @enderror">
            <div class="form-text">{{ __('管理画面と API の URL(例: https://admin.example.com)。ドメインと HTTPS は、外側のリバースプロキシで割り当ててください。') }}</div>
        </div>

        <div class="text-end">
            <button type="submit" class="btn btn-primary px-4">{{ __('次へ') }}</button>
        </div>
    </form>
@endsection
