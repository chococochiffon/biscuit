<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesQuestionAnswer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreQuestionAnswerRequest extends FormRequest
{
    use ValidatesQuestionAnswer;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     * 更新(UpdateQuestionAnswerRequest)と共通のルール。一意性などのチェックでは、更新対象(ルートのモデル。新規登録時は null)を除く。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->questionAnswerRules();
    }
}
