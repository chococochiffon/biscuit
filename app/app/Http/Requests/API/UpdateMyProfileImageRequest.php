<?php

namespace App\Http\Requests\API;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * マイページのアイコン画像の変更(切り抜き範囲は元画像のピクセル基準。1 つでも未指定なら中央で切り抜く)。
 */
class UpdateMyProfileImageRequest extends FormRequest
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
            'image' => ['required', 'image', 'max:10240'],
            'crop' => ['nullable', 'array'],
            'crop.x' => ['nullable', 'numeric', 'min:0'],
            'crop.y' => ['nullable', 'numeric', 'min:0'],
            'crop.width' => ['nullable', 'numeric', 'min:1'],
            'crop.height' => ['nullable', 'numeric', 'min:1'],
        ];
    }

    /**
     * 切り抜き範囲(1 つでも未指定なら null。中央で切り抜く)。
     *
     * @return array{x: float, y: float, width: float, height: float}|null
     */
    public function crop(): ?array
    {
        $crop = collect(['x', 'y', 'width', 'height'])->mapWithKeys(fn (string $key) => [$key => $this->validated("crop.{$key}")]);

        return $crop->contains(fn ($value) => blank($value)) ? null : $crop->map(fn ($value) => (float) $value)->all();
    }
}
