<?php

namespace App\Http\Requests;

use App\Enums\CallContentType;
use App\Rules\AllowedTableName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreContentModelRelationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content_type' => ['required', new Enum(CallContentType::class)],
            'model_name' => [
                'required', 'string', 'max:255',
                Rule::unique('content_model_relations', 'model_name')
                    ->where(fn ($query) => $query->where('content_type', $this->input('content_type')))
                    ->withoutTrashed(),
            ],
            'table_name' => ['required', 'string', 'max:255', new AllowedTableName],
        ];
    }
}
