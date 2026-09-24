@php
    /**
     * 公開側URLの親パス・スラッグの入力欄と、入力に合わせて更新されるURLのプレビュー(admin.js の initPathPreview)。
     *
     * @var string|null $parentPath 親パスの初期値
     * @var string|null $slug スラッグの初期値
     * @var bool $slugRequired スラッグを必須にするか(記事は任意で、未入力なら記事番号を使う)
     * @var string|null $slugFallback スラッグ未入力時にURLの末尾へ表示する値(記事番号など)
     */
    $parentPathValue = old('parent_path', $parentPath ?? '');
    $slugValue = old('slug', $slug ?? '');
    $slugFallback ??= null;
    $previewSlug = $slugValue !== '' && $slugValue !== null ? $slugValue : $slugFallback;
@endphp

<div class="mb-3">
    <label for="parent_path" class="form-label">{{ __('親パス') }}</label>
    <div class="input-group">
        <span class="input-group-text">/</span>
        <input
            id="parent_path"
            type="text"
            name="parent_path"
            value="{{ $parentPathValue }}"
            maxlength="255"
            class="form-control"
            placeholder="company"
            data-role="path-parent"
        >
    </div>
    <div class="form-text">{{ __('階層は「/」で区切ります(例: company/info)。未指定の場合はサイト直下になります。') }}</div>
</div>

<div class="mb-3">
    <label for="slug" @class(['form-label', 'is-required' => $slugRequired])>{{ __('スラッグ') }}</label>
    <div class="input-group">
        <span class="input-group-text">/</span>
        <input
            id="slug"
            type="text"
            name="slug"
            value="{{ $slugValue }}"
            @required($slugRequired)
            maxlength="255"
            class="form-control"
            placeholder="{{ $slugRequired ? 'about' : $slugFallback }}"
            data-role="path-slug"
        >
    </div>
    <div class="form-text">
        {{ __('半角英小文字・数字・ハイフンが使えます(数字だけは不可)。') }}
        @unless ($slugRequired)
            {{ __('未入力の場合は記事番号になります。') }}
        @endunless
        <br>
        {{ __('URL') }}:
        <code data-role="path-preview" data-fallback="{{ $slugFallback }}">{{ $previewSlug !== null ? \App\Models\SinglePage::buildPath($parentPathValue, $previewSlug) : '' }}</code>
    </div>
</div>
