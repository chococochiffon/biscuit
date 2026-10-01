@include('admin.partials._form_errors')

<div class="mb-3">
    <label for="name" class="form-label">{{ __('名前') }}</label>
    <input
        id="name"
        type="text"
        name="name"
        value="{{ old('name', $builderComponent->name ?? '') }}"
        required
        maxlength="100"
        class="form-control"
    >
    <div class="form-text">{{ __('ページビルダーでコンポーネントを選ぶときに表示します(例: お問い合わせへの案内)。') }}</div>
</div>

<div class="mb-3">
    <label for="description" class="form-label">{{ __('説明') }}</label>
    <input
        id="description"
        type="text"
        name="description"
        value="{{ old('description', $builderComponent->description ?? '') }}"
        maxlength="255"
        class="form-control"
    >
</div>
