<?php

namespace App\Http\Requests;

use App\Enums\BuilderContext;
use App\Support\Builder\BuilderValidator;
use App\Support\Builder\SchemaMigrator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * ページビルダーの編集中の内容(下書き)の保存。内容は BuilderValidator で検証し、
 * ノードを特定できるエラーは nodes.{ノードの ID}、それ以外は content のキーで返す(エディタがそのノードを選択して知らせる)。
 * updated_at は画面を開いた(または最後に保存した)ときの更新日時で、ほかの管理者が先に保存していないかの確認に使う。
 * 内容は送られたとおりに検証するため、空文字の null への変換と前後の空白の削除は bootstrap/app.php で止めている。
 * 行・カラムで流し込む配置(v1)の内容は保存しない(エディタが開いたときに自由配置(v2)へ変換する。公開中の v1 はそのまま公開側に出る)。
 */
class SavePageBuilderRequest extends FormRequest
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
            'content' => ['required', 'array'],
            'updated_at' => ['nullable', 'string'],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('content')) {
                    return;
                }

                if ($this->input('content.version') === SchemaMigrator::LEGACY_VERSION) {
                    $validator->errors()->add('content', __('行・カラムで並べる形式の内容は保存できません。画面を読み込み直すと、自由配置に変換します。'));

                    return;
                }

                foreach (app(BuilderValidator::class)->errors($this->input('content'), $this->context()) as $error) {
                    $validator->errors()->add($error['node'] === null ? 'content' : 'nodes.'.$error['node'], $error['message']);
                }
            },
        ];
    }

    /**
     * 内容を検証する文脈(コンポーネントの内容の保存では、そのコンポーネントの種類の文脈)。
     */
    protected function context(): BuilderContext
    {
        return BuilderContext::Page;
    }

    /**
     * 検証済みの内容を整形したもの(props の既定値を補い、テキストの HTML を無害化する)。
     *
     * @return array{version: int, children: list<array<string, mixed>>}
     */
    public function content(): array
    {
        return app(BuilderValidator::class)->normalize($this->validated('content'));
    }
}
