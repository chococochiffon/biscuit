<?php

namespace App\Http\Requests\API;

use App\Http\Requests\Concerns\ValidatesPath;
use App\Models\Article;
use App\Models\ArticlePathOption;
use App\Support\HtmlSanitizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * マイページの記事の新規作成。更新(UpdateMyArticleRequest)と共通のルール。
 * 親パスは入力させず、管理者が登録した投稿先(ArticlePathOption)を選ばせる。公開期間・公開ステータスも受け付けない。
 * 本文は検証の前に無害化する(公開側でそのまま HTML として表示するため)。
 */
class StoreMyArticleRequest extends FormRequest
{
    use ValidatesPath;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 本文を無害化し、スラッグを整え、選んだ投稿先の親パスを parent_path に入れる(スラッグの URL の重複判定に使う)。
     * 投稿先を選ばなかったときは、更新対象の記事の今の親パスを使う。
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'content' => HtmlSanitizer::cleanArticle(is_string($this->input('content')) ? $this->input('content') : null, Article::contentImageUrlPrefix()),
            'slug' => trim((string) $this->input('slug'), ' /') ?: null,
            'parent_path' => $this->selectedPathOption()?->parent_path ?? $this->article()?->parent_path,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'article_path_option_id' => [
                $this->article() === null ? 'required' : 'nullable',
                'integer',
                Rule::exists('article_path_options', 'id')->withoutTrashed(),
            ],
            'slug' => $this->pathRules(slugRequired: false, ignore: $this->article())['slug'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->pathMessages();
    }

    /**
     * 保存する親パス(選んだ投稿先の親パス。選ばなかったときは今の親パス)。
     */
    public function parentPath(): ?string
    {
        return $this->input('parent_path');
    }

    /**
     * 更新対象の記事(新規作成時は null)。
     */
    protected function article(): ?Article
    {
        $article = $this->route('myArticle');

        return $article instanceof Article ? $article : null;
    }

    /**
     * 選んだ投稿先(未選択・存在しないときは null。存在しないことは rules() で検証する)。
     */
    private function selectedPathOption(): ?ArticlePathOption
    {
        $id = $this->input('article_path_option_id');

        return is_numeric($id) ? ArticlePathOption::query()->find($id) : null;
    }
}
