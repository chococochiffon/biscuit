<?php

namespace App\Http\Resources;

use App\Models\SocialLink;
use App\Models\TopSliderImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class SiteSettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * SNS リンク(social_links)・トップスライダー画像(top_slider_images)はサイト全体で共通のため、並び順ですべて含める。
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'site_title' => $this->site_title,
            'description' => $this->description,
            'front_url' => $this->front_url,
            'api_url' => $this->api_url,
            'site_icon_url' => $this->site_icon ? Storage::disk('public')->url($this->site_icon) : null,
            'site_image_url' => $this->site_image ? Storage::disk('public')->url($this->site_image) : null,
            'social_links' => SocialLinkResource::collection(SocialLink::query()->ordered()->get()),
            'top_slider_images' => TopSliderImageResource::collection(TopSliderImage::query()->ordered()->get()),
        ];
    }
}
