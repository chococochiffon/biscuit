<?php

namespace App\Http\Resources;

use App\Models\CustomPages\CustomForm;
use App\Models\CustomPages\CustomPageDetail;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * カスタムページ 1 件。公開側で記事・固定ページと同じ部品で表示できるよう、
 * 記事型は ArticleResource、固定ページ型は SinglePageResource と同じ項目名で返し、custom_page_type で種類を示す。
 * 詳細ページ(パス解決 API)では、詳細(固定ページ型)とカスタムフォームの項目(custom_fields)も含める。
 */
class CustomPageEntryResource extends JsonResource
{
    /**
     * @var Collection<int, CustomPageDetail>|null
     */
    private ?Collection $details = null;

    /**
     * @var list<array{name: string, type: string, value: mixed}>|null
     */
    private ?array $customFields = null;

    /**
     * 詳細(固定ページ型)を含める。
     *
     * @param  Collection<int, CustomPageDetail>  $details
     */
    public function withDetails(Collection $details): static
    {
        $this->details = $details;

        return $this;
    }

    /**
     * カスタムフォームの項目(並び順。項目名・入力形式・入力値)を含める。未入力の項目は value が null(チェックボックスは空の配列)。
     *
     * @param  Collection<int, CustomForm>  $forms
     * @param  Collection<int|string, mixed>  $values  項目の id をキーにした入力値
     */
    public function withCustomFields(Collection $forms, Collection $values): static
    {
        $this->customFields = $forms
            ->map(fn (CustomForm $form) => [
                'name' => $form->parts_name,
                'type' => $form->customs_form_type->apiName(),
                'value' => $values[$form->id] ?? null,
            ])
            ->values()
            ->all();

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $type = $this->customPageType();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'path' => $this->path(),
            'custom_page_type' => new CustomPageTypeResource($type),
            ...($type->hasDetails()
                ? [
                    'short_sentences' => $this->short_sentences,
                    'header_image_url' => $this->header_image_url,
                    'details' => $this->when($this->details !== null, fn () => SinglePageDetailResource::collection($this->details)),
                ]
                : [
                    'content' => $this->content,
                    'thumbnail_url' => $this->thumbnail_url,
                    'author_name' => null,
                    'author' => null,
                    'tags' => [],
                    'published_at' => $this->publication_start_datetime?->toIso8601String(),
                    'updated_at' => $this->updated_at?->toIso8601String(),
                ]),
            'custom_fields' => $this->when($this->customFields !== null, fn () => $this->customFields),
        ];
    }
}
