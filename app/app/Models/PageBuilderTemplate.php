<?php

namespace App\Models;

use App\Models\Concerns\StoresReadableJson;
use Database\Factories\PageBuilderTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ページビルダーのテンプレート(ページ全体の内容のひな形)。エディタで選ぶと内容を写して下書きにする。
 * 最初のテンプレートは PageBuilderTemplateSeeder が登録し、エディタから今のページをテンプレートとして保存・削除できる。
 */
#[Fillable(['name', 'description', 'schema_version', 'content'])]
class PageBuilderTemplate extends Model
{
    /** @use HasFactory<PageBuilderTemplateFactory> */
    use HasFactory, SoftDeletes, StoresReadableJson;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schema_version' => 'integer',
            'content' => 'array',
        ];
    }
}
