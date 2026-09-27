@include('admin.partials._form_errors')

<div class="mb-3">
    <label for="tag_name" class="form-label">{{ __('タグ名') }}</label>
    <input
        id="tag_name"
        type="text"
        name="tag_name"
        value="{{ old('tag_name', $tag->tag_name ?? '') }}"
        required
        class="form-control"
    >
</div>
