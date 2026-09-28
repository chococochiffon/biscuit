@php
    /**
     * カスタムフォームの 1 項目の入力欄(入力形式ごとに出し分ける)。
     *
     * @var \App\Models\CustomPages\CustomForm $form
     * @var mixed $value 保存済みの入力値(入力エラーで戻った場合は入力値)
     */
    $name = "custom_fields[{$form->id}]";
    $inputId = "custom_field_{$form->id}";
    $type = $form->customs_form_type;
@endphp

<div class="mb-3">
    @if (in_array($type, [\App\Enums\CustomFormType::Radio, \App\Enums\CustomFormType::Checkbox], true))
        <label class="form-label d-block">{{ $form->parts_name }}</label>
        @foreach ($form->options() as $optionIndex => $option)
            <div class="form-check form-check-inline">
                @if ($type === \App\Enums\CustomFormType::Radio)
                    <input id="{{ $inputId }}_{{ $optionIndex }}" type="radio" name="{{ $name }}" value="{{ $option }}" class="form-check-input" @checked($value === $option)>
                @else
                    <input id="{{ $inputId }}_{{ $optionIndex }}" type="checkbox" name="{{ $name }}[]" value="{{ $option }}" class="form-check-input" @checked(in_array($option, (array) $value, true))>
                @endif
                <label for="{{ $inputId }}_{{ $optionIndex }}" class="form-check-label">{{ $option }}</label>
            </div>
        @endforeach
    @else
        <label for="{{ $inputId }}" class="form-label">{{ $form->parts_name }}</label>
        @switch($type)
            @case(\App\Enums\CustomFormType::Textarea)
                <textarea id="{{ $inputId }}" name="{{ $name }}" rows="4" class="form-control">{{ $value }}</textarea>
                @break
            @case(\App\Enums\CustomFormType::Select)
                <select id="{{ $inputId }}" name="{{ $name }}" class="form-select form-select-auto">
                    <option value="">{{ __('選択してください') }}</option>
                    @foreach ($form->options() as $option)
                        <option value="{{ $option }}" @selected($value === $option)>{{ $option }}</option>
                    @endforeach
                </select>
                @break
            @default
                <input
                    id="{{ $inputId }}"
                    type="{{ match ($type) { \App\Enums\CustomFormType::Date => 'date', \App\Enums\CustomFormType::Email => 'email', default => 'text' } }}"
                    name="{{ $name }}"
                    value="{{ $value }}"
                    @if ($type === \App\Enums\CustomFormType::Text || $type === \App\Enums\CustomFormType::Email) maxlength="255" @endif
                    class="form-control"
                >
        @endswitch
    @endif
</div>
