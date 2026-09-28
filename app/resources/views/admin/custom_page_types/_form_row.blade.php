@php
    /**
     * カスタムフォームの項目定義の 1 行。
     *
     * @var string $index
     * @var int|string|null $id
     * @var string|null $partsName
     * @var int|string|null $formType
     * @var string|null $options 選択肢(1 行に 1 つ)
     * @var int $sortOrder
     */
@endphp

<div class="row g-2 align-items-start mb-2 border-bottom pb-2" data-role="repeater-row">
    @if ($id)
        <input type="hidden" name="forms[{{ $index }}][id]" value="{{ $id }}">
    @endif
    <input type="hidden" name="forms[{{ $index }}][sort_order]" value="{{ $sortOrder }}" data-role="sort-order">

    <div class="col-auto align-self-stretch d-flex">
        <span class="single-page-detail-handle" data-role="drag-handle" title="{{ __('ドラッグして並び替え') }}">
            <i class="bi bi-grip-vertical"></i>
        </span>
    </div>

    <div class="col-md-4">
        <label class="form-label small">{{ __('項目名') }}</label>
        <input type="text" name="forms[{{ $index }}][parts_name]" value="{{ $partsName }}" class="form-control form-control-sm" maxlength="255" required>
    </div>

    <div class="col-auto">
        <label class="form-label small">{{ __('入力形式') }}</label>
        <select name="forms[{{ $index }}][customs_form_type]" class="form-select form-select-sm form-select-auto" required>
            @foreach (\App\Enums\CustomFormType::cases() as $customFormType)
                <option value="{{ $customFormType->value }}" @selected((string) $formType === (string) $customFormType->value)>{{ $customFormType->label() }}</option>
            @endforeach
        </select>
    </div>

    <div class="col">
        <label class="form-label small">{{ __('選択肢(1行に1つ)') }}</label>
        <textarea name="forms[{{ $index }}][options]" rows="2" class="form-control form-control-sm" placeholder="{{ __('プルダウン・ラジオ・チェックボックスのときに入力') }}">{{ $options }}</textarea>
    </div>

    <div class="col-auto pt-4">
        <button type="button" class="btn btn-outline-danger btn-sm" data-role="remove-row">−</button>
    </div>
</div>
