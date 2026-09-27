<?php

namespace App\Models;

use App\Enums\SocialService;
use App\Models\Concerns\HasSortOrder;
use Database\Factories\SocialLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['service', 'name', 'url', 'sort_order'])]
class SocialLink extends Model
{
    /** @use HasFactory<SocialLinkFactory> */
    use HasFactory, HasSortOrder, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service' => SocialService::class,
        ];
    }
}
