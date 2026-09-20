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
    <label for="site_title" class="form-label">{{ __('サイトタイトル') }}</label>
    <input
        id="site_title"
        type="text"
        name="site_title"
        value="{{ old('site_title', $siteSetting->site_title ?? '') }}"
        required
        class="form-control"
    >
</div>

<div class="mb-3">
    <label for="description" class="form-label">{{ __('説明') }}</label>
    <textarea id="description" name="description" rows="4" class="form-control">{{ old('description', $siteSetting->description ?? '') }}</textarea>
</div>

<div class="mb-3">
    <label for="site_icon" class="form-label">{{ __('サイトアイコン') }}</label>
    @isset($siteSetting)
        @if ($siteSetting->site_icon)
            <div class="mb-2">
                <img
                    src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($siteSetting->site_icon) }}"
                    alt="{{ __('サイトアイコン') }}"
                    class="img-thumbnail"
                    style="width: 96px; height: 96px; object-fit: cover;"
                >
            </div>
        @endif
    @endisset
    <input id="site_icon" type="file" name="site_icon" accept="image/*" class="form-control">
</div>

<div class="mb-3">
    <label for="site_image" class="form-label">{{ __('サイト画像') }}</label>
    @isset($siteSetting)
        @if ($siteSetting->site_image)
            <div class="mb-2">
                <img
                    src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($siteSetting->site_image) }}"
                    alt="{{ __('サイト画像') }}"
                    class="img-thumbnail"
                    style="width: 240px; height: 160px; object-fit: cover;"
                >
            </div>
        @endif
    @endisset
    <input id="site_image" type="file" name="site_image" accept="image/*" class="form-control">
</div>
