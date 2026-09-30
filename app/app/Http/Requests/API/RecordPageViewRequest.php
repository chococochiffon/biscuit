<?php

namespace App\Http\Requests\API;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PV の記録(chococo のサーバーが、公開側のページを表示したときに中継する)。
 * 閲覧者の IP アドレス・User-Agent を本文で受け取るため、共有の鍵(X-Page-View-Key と config/page_views.php の forward_key)が
 * 一致する chococo のサーバーからのリクエストだけを受け付ける(鍵が未設定なら受け付けない)。
 */
class RecordPageViewRequest extends FormRequest
{
    public const KEY_HEADER = 'X-Page-View-Key';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $key = (string) config('page_views.forward_key');

        return $key !== '' && hash_equals($key, (string) $this->header(self::KEY_HEADER));
    }

    /**
     * Get the validation rules that apply to the request.
     * User-Agent・Referer は長すぎても記録するときに切り詰め、訪問者の識別子は UUID でなければ発行し直すため、ここでは弾かない。
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'path' => ['required', 'string', 'max:255'],
            'visitor_id' => ['nullable', 'string', 'max:64'],
            'session_id' => ['nullable', 'string', 'max:128'],
            'ip' => ['nullable', 'ip'],
            'user_agent' => ['nullable', 'string'],
            'referer' => ['nullable', 'string'],
        ];
    }
}
