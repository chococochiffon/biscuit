<?php

namespace App\Rules;

use App\Models\Article;
use App\Models\SinglePage;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

class AvailablePath implements DataAwareRule, ValidationRule
{
    /**
     * 公開側URLのパスを持つモデル。パスはこれらすべての間で重ならないようにする。
     *
     * @var list<class-string<Model>>
     */
    private const PATH_MODELS = [SinglePage::class, Article::class];

    /**
     * バリデーション対象の全入力値。
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * @param  Model|null  $ignore  更新時の自分自身(重複判定から除外する)
     */
    public function __construct(private ?Model $ignore = null) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * 親パスとスラッグから組み立てたパスが、論理削除されていない他の記事・固定ページのURLと重ならないか検証する。
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $path = SinglePage::buildPath($this->data['parent_path'] ?? null, (string) $value);

        foreach (self::PATH_MODELS as $modelClass) {
            $isTaken = $modelClass::query()
                ->where('path', $path)
                ->when($this->ignore instanceof $modelClass, fn ($query) => $query->whereKeyNot($this->ignore->getKey()))
                ->exists();

            if ($isTaken) {
                $fail(__('URL「:path」は他の記事または固定ページで使われています。', ['path' => $path]));

                return;
            }
        }
    }
}
