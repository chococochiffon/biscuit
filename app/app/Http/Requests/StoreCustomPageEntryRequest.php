<?php

namespace App\Http\Requests;

use App\Enums\ArticleApprovalStatus;
use App\Enums\CustomFormType;
use App\Models\CustomPages\CustomForm;
use App\Models\CustomPageType;
use App\Models\SinglePage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * カスタムページの登録・更新。ベースの型(記事/固定ページ)の項目と、種類ごとのカスタムフォームの項目(custom_fields[項目の id])を検証する。
 */
class StoreCustomPageEntryRequest extends FormRequest
{
    /**
     * @var Collection<int, CustomForm>|null
     */
    private ?Collection $forms = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * スラッグの前後の空白と「/」を除く(未入力は null)。
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['slug' => trim((string) $this->input('slug'), ' /') ?: null]);
    }

    /**
     * Get the validation rules that apply to the request.
     * 更新(UpdateCustomPageEntryRequest)と共通のルール。
     * スラッグは公開側の URL(/カスタム名の複数形/スラッグ)に使い、種類の中で一意にする(固定ページ型は必須、記事型は未入力なら id)。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $type = $this->customPageType();

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                $type->hasDetails() ? 'required' : 'nullable',
                'string', 'max:255',
                'regex:#^'.SinglePage::SLUG_PATTERN.'$#',
                'not_regex:#^[0-9]+$#',
                Rule::unique($type->tableName(), 'slug')->ignore($this->route('entry'))->withoutTrashed(),
            ],
            ...($type->hasDetails() ? $this->singlePageRules($type) : $this->articleRules()),
            'publication_start_datetime' => ['required', 'date_format:Y-m-d H:i'],
            'publication_end_datetime' => ['nullable', 'date_format:Y-m-d H:i', 'after:publication_start_datetime'],
            'custom_fields' => ['nullable', 'array'],
            ...$this->customFieldRules(),
        ];
    }

    /**
     * カスタムフォームの項目名を、エラーメッセージの項目名にする。
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->forms()
            ->flatMap(fn (CustomForm $form) => [
                "custom_fields.{$form->id}" => $form->parts_name,
                "custom_fields.{$form->id}.*" => $form->parts_name,
            ])
            ->all();
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'details.max' => __('詳細は:max件まで登録できます。'),
            'slug.regex' => __('スラッグは半角英小文字・数字・ハイフンで入力してください。'),
            'slug.not_regex' => __('数字だけのスラッグは記事番号用のため使えません。'),
        ];
    }

    /**
     * ルートのカスタムページの種類。
     */
    public function customPageType(): CustomPageType
    {
        return $this->route('customPageType');
    }

    /**
     * この種類のカスタムフォームの項目定義(並び順)。
     *
     * @return Collection<int, CustomForm>
     */
    public function forms(): Collection
    {
        return $this->forms ??= CustomForm::queryFor($this->customPageType())->ordered()->get();
    }

    /**
     * @return array<string, array<mixed>>
     */
    private function articleRules(): array
    {
        return [
            'content' => ['required', 'string'],
            'thumbnail' => ['nullable', 'image', 'max:10240'],
            'approval' => ['required', new Enum(ArticleApprovalStatus::class)],
        ];
    }

    /**
     * 詳細の id は、このページの詳細だけを更新できる(新規登録時は既存の詳細を指定できない)。
     *
     * @return array<string, array<mixed>>
     */
    private function singlePageRules(CustomPageType $type): array
    {
        return [
            'short_sentences' => ['required', 'string', 'max:255'],
            'header_image' => ['nullable', 'image', 'max:10240'],
            'details' => ['nullable', 'array', 'max:'.config('limits.single_page_details')],
            'details.*.id' => [
                'nullable', 'integer',
                Rule::exists($type->detailsTableName(), 'id')
                    ->where($type->entryForeignKey(), $this->route('entry'))
                    ->withoutTrashed(),
            ],
            'details.*.sub_title' => ['required', 'string', 'max:255'],
            'details.*.contents' => ['nullable', 'string'],
            'details.*.sort_order' => ['nullable', 'integer'],
        ];
    }

    /**
     * カスタムフォームの項目ごとのルール(どの項目も任意入力)。選択肢を持つ項目は、定義した選択肢の中からだけ選べる。
     *
     * @return array<string, array<mixed>>
     */
    private function customFieldRules(): array
    {
        return $this->forms()
            ->flatMap(fn (CustomForm $form) => match ($form->customs_form_type) {
                CustomFormType::Text => ["custom_fields.{$form->id}" => ['nullable', 'string', 'max:255']],
                CustomFormType::Date => ["custom_fields.{$form->id}" => ['nullable', 'date_format:Y-m-d']],
                CustomFormType::Textarea => ["custom_fields.{$form->id}" => ['nullable', 'string', 'max:65535']],
                CustomFormType::Email => ["custom_fields.{$form->id}" => ['nullable', 'email', 'max:255']],
                CustomFormType::Select, CustomFormType::Radio => ["custom_fields.{$form->id}" => ['nullable', 'string', Rule::in($form->options())]],
                CustomFormType::Checkbox => [
                    "custom_fields.{$form->id}" => ['nullable', 'array'],
                    "custom_fields.{$form->id}.*" => ['string', Rule::in($form->options())],
                ],
            })
            ->all();
    }
}
