<?php

namespace App\Support\Builder;

use App\Models\GalleryCategory;
use App\Models\PageBuilder;
use App\Models\PageBuilderComponent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * ページビルダーの内容の書き出し(Export)と読み込み(Import)。別のサイト・環境へ移せるよう、書き出すファイル(JSON)には
 * 内容に加えて、使っている画像(data URI)・グローバルコンポーネントの名前と公開中の内容・ギャラリーの分類の名前を入れる。
 *
 * 読み込みでは次のように今のサイトに合わせてから検証し、整形した内容を返す(保存はしない。エディタが下書きを置き換える)。
 * - グローバルコンポーネント: 同じ名前のコンポーネントがあればそれを使い、なければ中身のセクションをページに展開する
 *   (コンポーネントの中にはコンポーネントを置けないため、コンポーネントへの読み込みでは常に展開する)
 * - ギャラリーの分類: 同じ名前の分類があればそれを使い、なければ「すべて」にする
 * - 画像: ファイルに入っている画像を登録し直す(入っていなければ、同じパスの画像がこのサイトにあればそのまま使い、なければ外す)
 * - ブロックの ID はすべて振り直す
 */
final class BuilderTransfer
{
    /**
     * 書き出すファイルの目印と形式の版。
     */
    public const FORMAT = 'biscuit-page-builder';

    public const FORMAT_VERSION = 1;

    /**
     * ファイルに入れる画像の形式(拡張子 → MIME タイプ)。
     */
    private const IMAGE_TYPES = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];

    /**
     * 読み込みで知らせること(展開したコンポーネント・外した画像など)。
     *
     * @var list<string>
     */
    private array $warnings = [];

    /**
     * 読み込みで登録した画像(ファイルのパス → 登録したパス。使えなければ null)。同じ画像は 1 回だけ登録する。
     *
     * @var array<string, string|null>
     */
    private array $storedPaths = [];

    /**
     * 書き出すファイルの中身。内容は検証・整形済みであること。
     *
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    public function export(array $content, string $title): array
    {
        $components = [];
        $categories = [];

        foreach (BuilderContent::nodes($content) as $node) {
            if ($node['type'] === 'global' && is_int($node['props']['component'] ?? null)) {
                $component = PageBuilderComponent::query()->find($node['props']['component']);

                if ($component !== null) {
                    $components[$component->id] = ['name' => $component->name, 'content' => $component->published_content];
                }
            }

            if ($node['type'] === 'gallery' && is_int($node['props']['category'] ?? null)) {
                $name = GalleryCategory::query()->whereKey($node['props']['category'])->value('name');

                if ($name !== null) {
                    $categories[$node['props']['category']] = $name;
                }
            }
        }

        $imagePaths = BuilderContent::imagePaths($content);

        foreach ($components as $component) {
            array_push($imagePaths, ...BuilderContent::imagePaths($component['content']));
        }

        return [
            'format' => self::FORMAT,
            'formatVersion' => self::FORMAT_VERSION,
            'exportedAt' => Date::now()->toIso8601String(),
            'title' => $title,
            'content' => $content,
            'components' => (object) $components,
            'galleryCategories' => (object) $categories,
            'images' => (object) $this->exportImages(array_unique($imagePaths)),
        ];
    }

    /**
     * 書き出したファイルの中身を今のサイトに合わせ、検証・整形した内容を返す。ファイルの形・内容が正しくなければ例外にする
     * (例外の文言はそのまま画面に出す)。画像は内容が正しいと分かってから登録する。
     *
     * @return array{content: array{version: int, children: list<array<string, mixed>>}, warnings: list<string>, images: int}
     *
     * @throws InvalidArgumentException
     */
    public function import(mixed $data, BuilderValidator $validator, bool $allowsGlobal = true): array
    {
        $this->warnings = [];
        $this->storedPaths = [];

        if (! is_array($data) || ($data['format'] ?? null) !== self::FORMAT || ! is_array($data['content'] ?? null)) {
            throw new InvalidArgumentException(__('ページビルダーから書き出したファイルではありません。'));
        }

        if (($data['formatVersion'] ?? null) !== self::FORMAT_VERSION) {
            throw new InvalidArgumentException(__('このファイルの形式(:version)には対応していません。', ['version' => json_encode($data['formatVersion'] ?? null)]));
        }

        $content = $this->migrate($data['content']);
        $components = is_array($data['components'] ?? null) ? $data['components'] : [];
        $categories = is_array($data['galleryCategories'] ?? null) ? $data['galleryCategories'] : [];

        $content['children'] = $this->resolveComponents($content['children'] ?? [], $components, $allowsGlobal);
        $content['children'] = $this->mapNodes($content['children'], fn (array $node) => $this->resolveGalleryCategory($node, $categories));

        $errors = $validator->errors($content, $allowsGlobal);

        if ($errors !== []) {
            throw new InvalidArgumentException($errors[0]['message']);
        }

        $images = is_array($data['images'] ?? null) ? $data['images'] : [];
        $content['children'] = $this->mapNodes($content['children'], fn (array $node) => $this->importImages($node, $images));

        return [
            'content' => $validator->normalize($content),
            'warnings' => array_values(array_unique($this->warnings)),
            'images' => count(array_filter($this->storedPaths)),
        ];
    }

    /**
     * @param  list<string>  $paths
     * @return array<string, string>
     */
    private function exportImages(array $paths): array
    {
        $disk = Storage::disk('public');
        $images = [];

        foreach ($paths as $path) {
            $type = self::IMAGE_TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;

            // パスは BuilderValidator が image/ 配下の画像の形だけに絞っている
            if ($type !== null && preg_match(BuilderContent::IMAGE_PATH_PATTERN, $path) === 1 && $disk->exists($path)) {
                $images[$path] = 'data:'.$type.';base64,'.base64_encode((string) $disk->get($path));
            }
        }

        return $images;
    }

    /**
     * @return array<string, mixed>
     */
    private function migrate(mixed $content): array
    {
        try {
            return (new SchemaMigrator)->migrate(is_array($content) ? $content : []);
        } catch (InvalidArgumentException) {
            throw new InvalidArgumentException(__('このファイルの内容の版には対応していません。'));
        }
    }

    /**
     * ページの直下のグローバルコンポーネントのブロックを、同じ名前のコンポーネントにつなぎ直すか、中身のセクションに展開する。
     *
     * @param  list<mixed>  $children
     * @param  array<array-key, mixed>  $components
     * @return list<mixed>
     */
    private function resolveComponents(array $children, array $components, bool $allowsGlobal): array
    {
        $resolved = [];

        foreach ($children as $node) {
            if (! is_array($node) || ($node['type'] ?? null) !== 'global') {
                $resolved[] = $node;

                continue;
            }

            $exported = $components[$node['props']['component'] ?? ''] ?? null;
            $name = is_array($exported) && is_string($exported['name'] ?? null) ? $exported['name'] : null;
            $local = $allowsGlobal && $name !== null ? PageBuilderComponent::query()->where('name', $name)->orderBy('id')->first() : null;

            if ($local !== null && is_array($node['props'] ?? null)) {
                $node['props']['component'] = $local->id;
                $resolved[] = $node;
            } elseif (is_array($exported['content'] ?? null)) {
                array_push($resolved, ...$this->migrate($exported['content'])['children'] ?? []);
                $this->warnings[] = __('グローバルコンポーネント「:name」が見つからないため、中身をページに展開しました。', ['name' => $name]);
            } else {
                $this->warnings[] = $name === null
                    ? __('参照先の分からないグローバルコンポーネントのブロックを外しました。')
                    : __('グローバルコンポーネント「:name」は中身がない(未公開)ため、ブロックを外しました。', ['name' => $name]);
            }
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array<array-key, mixed>  $categories
     * @return array<string, mixed>
     */
    private function resolveGalleryCategory(array $node, array $categories): array
    {
        if ($node['type'] !== 'gallery' || ! is_int($node['props']['category'] ?? null)) {
            return $node;
        }

        $name = $categories[$node['props']['category']] ?? null;
        $localId = is_string($name) ? GalleryCategory::query()->where('name', $name)->orderBy('id')->value('id') : null;
        $node['props']['category'] = $localId;

        if ($localId === null) {
            $this->warnings[] = is_string($name)
                ? __('ギャラリーの分類「:name」が見つからないため、「すべて」にしました。', ['name' => $name])
                : __('分からないギャラリーの分類を「すべて」にしました。');
        }

        return $node;
    }

    /**
     * ノードの画像の項目を、ファイルの画像を登録し直したパスに置き換える(同じ画像は 1 回だけ登録する)。
     *
     * @param  array<string, mixed>  $node
     * @param  array<array-key, mixed>  $images
     * @return array<string, mixed>
     */
    private function importImages(array $node, array $images): array
    {
        foreach (BlockRegistry::get($node['type'])['props'] ?? [] as $name => $prop) {
            $path = $node['props'][$name] ?? null;

            if ($prop['type'] !== 'image' || ! is_string($path)) {
                continue;
            }

            if (! array_key_exists($path, $this->storedPaths)) {
                $this->storedPaths[$path] = is_string($images[$path] ?? null) ? $this->storeImage($images[$path]) : null;
            }

            $node['props'][$name] = $this->storedPaths[$path] ?? (Storage::disk('public')->exists($path) ? $path : null);

            if ($node['props'][$name] === null) {
                $this->warnings[] = __('ファイルに入っていない画像を外しました。');
            }
        }

        return $node;
    }

    /**
     * data URI の画像を、ビルダーの画像としてアップロードと同じく登録する(画像として読めなければ null)。
     */
    private function storeImage(string $dataUri): ?string
    {
        if (preg_match('#\Adata:(image/(?:png|jpeg|gif|webp));base64,([A-Za-z0-9+/=]+)\z#', $dataUri, $matches) !== 1) {
            return null;
        }

        $binary = base64_decode($matches[2], true);

        if ($binary === false || strlen($binary) > (int) config('limits.image_max_kilobytes') * 1024 || @getimagesizefromstring($binary) === false) {
            return null;
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'builder-import-');

        try {
            file_put_contents($temporaryPath, $binary);

            return PageBuilder::storeImage(new UploadedFile($temporaryPath, 'imported', $matches[1], null, true));
        } finally {
            @unlink($temporaryPath);
        }
    }

    /**
     * 木のすべてのノードを親から順に変換し、ID を振り直す(変換は形の正しいノードだけに行い、ほかは検証に任せる)。
     *
     * @param  list<mixed>  $children
     * @param  callable(array<string, mixed>): array<string, mixed>  $callback
     * @return list<mixed>
     */
    private function mapNodes(array $children, callable $callback): array
    {
        return array_map(function (mixed $node) use ($callback) {
            if (! is_array($node) || ! is_string($node['type'] ?? null) || ! BlockRegistry::has($node['type'])) {
                return $node;
            }

            $node = $callback($node);
            $node['id'] = BuilderContent::newId($node['type']);

            if (is_array($node['children'] ?? null)) {
                $node['children'] = $this->mapNodes($node['children'], $callback);
            }

            return $node;
        }, $children);
    }
}
