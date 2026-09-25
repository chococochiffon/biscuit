<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSiteSettingRequest;
use App\Http\Requests\UpdateSiteSettingRequest;
use App\Models\CallContent;
use App\Models\ContentModelRelation;
use App\Models\SiteSetting;
use App\Models\SocialLink;
use App\Models\TopSliderImage;
use App\Rules\AllowedTableName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

class SiteSettingController extends Controller
{
    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $callContents = collect();
        $socialLinks = collect();
        $topSliderImages = collect();
        $contentModelRelations = ContentModelRelation::all(['id', 'content_type', 'model_name']);
        $tableNames = AllowedTableName::availableTables();

        return view('admin.site_settings.create', compact('callContents', 'socialLinks', 'topSliderImages', 'contentModelRelations', 'tableNames'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSiteSettingRequest $request): RedirectResponse
    {
        $siteSetting = SiteSetting::create([
            'site_title' => $request->validated('site_title'),
            'description' => $request->validated('description'),
            'front_url' => $request->validated('front_url'),
            'api_url' => $request->validated('api_url'),
        ]);

        if ($request->hasFile('site_icon')) {
            $siteSetting->update(['site_icon' => $siteSetting->storeSiteIcon($request->file('site_icon'))]);
        }

        if ($request->hasFile('site_image')) {
            $siteSetting->update(['site_image' => $siteSetting->storeSiteImage($request->file('site_image'))]);
        }

        $this->syncCallContents($request->validated('call_contents', []));
        $this->syncSocialLinks($request->validated('social_links', []));
        $this->syncTopSliderImages($request->validated('top_slider_images', []));

        return redirect()->route('admin.site-settings.show', $siteSetting)->with('status', 'サイト設定を登録しました。');
    }

    /**
     * Display the specified resource.
     */
    public function show(SiteSetting $siteSetting): View
    {
        $callContents = CallContent::query()->with('contentModelRelation')->orderBy('place')->orderBy('sort_order')->orderBy('id')->get();
        $socialLinks = SocialLink::query()->ordered()->get();
        $topSliderImages = TopSliderImage::query()->ordered()->get();

        return view('admin.site_settings.show', compact('siteSetting', 'callContents', 'socialLinks', 'topSliderImages'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SiteSetting $siteSetting): View
    {
        $callContents = CallContent::query()->orderBy('sort_order')->orderBy('id')->get();
        $socialLinks = SocialLink::query()->ordered()->get();
        $topSliderImages = TopSliderImage::query()->ordered()->get();
        $contentModelRelations = ContentModelRelation::all(['id', 'content_type', 'model_name']);
        $tableNames = AllowedTableName::availableTables();

        return view('admin.site_settings.edit', compact('siteSetting', 'callContents', 'socialLinks', 'topSliderImages', 'contentModelRelations', 'tableNames'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSiteSettingRequest $request, SiteSetting $siteSetting): RedirectResponse
    {
        $siteSetting->fill([
            'site_title' => $request->validated('site_title'),
            'description' => $request->validated('description'),
            'front_url' => $request->validated('front_url'),
            'api_url' => $request->validated('api_url'),
        ]);

        if ($request->hasFile('site_icon')) {
            $siteSetting->site_icon = $siteSetting->storeSiteIcon($request->file('site_icon'));
        }

        if ($request->hasFile('site_image')) {
            $siteSetting->site_image = $siteSetting->storeSiteImage($request->file('site_image'));
        }

        $siteSetting->save();

        $this->syncCallContents($request->validated('call_contents', []));
        $this->syncSocialLinks($request->validated('social_links', []));
        $this->syncTopSliderImages($request->validated('top_slider_images', []));

        return redirect()->route('admin.site-settings.show', $siteSetting)->with('status', 'サイト設定を更新しました。');
    }

    /**
     * フォームから送信された呼び出しコンテンツ(call_contents)の内容にデータベースを同期する。
     * 送信された行はid有無で作成/更新し、送信されなかった既存行は削除する。
     * 並び順(sort_order)は画面上の行の順(未送信の場合は送信順)で保存する。
     *
     * @param  array<int, array{id?: int|string|null, call_type: int|string, call_name: string, title?: string|null, subtitle?: string|null, content_model_relation_id: int|string, view_count: int|string, place: int|string, sort_order?: int|string|null}>  $rows
     */
    private function syncCallContents(array $rows): void
    {
        $submittedIds = collect($rows)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

        CallContent::query()->whereNotIn('id', $submittedIds)->delete();

        foreach (array_values($rows) as $index => $row) {
            $attributes = [
                'call_type' => $row['call_type'],
                'call_name' => $row['call_name'],
                'title' => $row['title'] ?? null,
                'subtitle' => $row['subtitle'] ?? null,
                'content_model_relation_id' => $row['content_model_relation_id'],
                'view_count' => $row['view_count'],
                'place' => $row['place'],
                'sort_order' => $row['sort_order'] ?? $index,
            ];

            if (! empty($row['id'])) {
                CallContent::query()->whereKey($row['id'])->update($attributes);
            } else {
                CallContent::create($attributes);
            }
        }
    }

    /**
     * フォームから送信されたSNSリンク(social_links)の内容にデータベースを同期する。
     * 送信された行はid有無で作成/更新し、送信されなかった既存行は削除する。
     * 並び順(sort_order)は画面上の行の順(未送信の場合は送信順)で保存する。
     *
     * @param  array<int, array{id?: int|string|null, service: int|string, name: string, url: string, sort_order?: int|string|null}>  $rows
     */
    private function syncSocialLinks(array $rows): void
    {
        $submittedIds = collect($rows)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

        SocialLink::query()->whereNotIn('id', $submittedIds)->delete();

        foreach (array_values($rows) as $index => $row) {
            $attributes = [
                'service' => $row['service'],
                'name' => $row['name'],
                'url' => $row['url'],
                'sort_order' => $row['sort_order'] ?? $index,
            ];

            if (! empty($row['id'])) {
                SocialLink::query()->whereKey($row['id'])->update($attributes);
            } else {
                SocialLink::create($attributes);
            }
        }
    }

    /**
     * フォームから送信されたトップスライダー画像(top_slider_images)の内容にデータベースを同期する。
     * 送信された行はid有無で作成/更新し、送信されなかった既存行は削除する。
     * 画像が選択された行だけ、指定された切り抜き範囲(未指定なら中央)で16:9に加工して保存し直す。
     * 並び順(sort_order)は画面上の行の順(未送信の場合は送信順)で保存する。
     *
     * @param  array<int, array{id?: int|string|null, image?: UploadedFile|null, url?: string|null, crop_x?: int|float|string|null, crop_y?: int|float|string|null, crop_width?: int|float|string|null, crop_height?: int|float|string|null, sort_order?: int|string|null}>  $rows
     */
    private function syncTopSliderImages(array $rows): void
    {
        $submittedIds = collect($rows)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

        TopSliderImage::query()->whereNotIn('id', $submittedIds)->delete();

        foreach (array_values($rows) as $index => $row) {
            $attributes = [
                'url' => $row['url'] ?? null,
                'sort_order' => $row['sort_order'] ?? $index,
            ];

            if (($row['image'] ?? null) instanceof UploadedFile) {
                $attributes['top_image'] = TopSliderImage::storeImage($row['image'], $this->cropFromRow($row));
            }

            if (! empty($row['id'])) {
                TopSliderImage::query()->whereKey($row['id'])->update($attributes);
            } else {
                TopSliderImage::create($attributes);
            }
        }
    }

    /**
     * 行の切り抜き範囲(crop_x/crop_y/crop_width/crop_height)を返す。1つでも未指定なら null(中央で切り抜く)。
     *
     * @param  array<string, mixed>  $row
     * @return array{x: float, y: float, width: float, height: float}|null
     */
    private function cropFromRow(array $row): ?array
    {
        foreach (['crop_x', 'crop_y', 'crop_width', 'crop_height'] as $key) {
            if (! isset($row[$key]) || $row[$key] === '') {
                return null;
            }
        }

        return [
            'x' => (float) $row['crop_x'],
            'y' => (float) $row['crop_y'],
            'width' => (float) $row['crop_width'],
            'height' => (float) $row['crop_height'],
        ];
    }
}
