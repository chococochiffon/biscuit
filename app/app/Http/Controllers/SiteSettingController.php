<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSiteSettingRequest;
use App\Http\Requests\UpdateSiteSettingRequest;
use App\Models\CallContent;
use App\Models\ContentModelRelation;
use App\Models\SiteSetting;
use App\Models\SocialLink;
use App\Rules\AllowedTableName;
use Illuminate\Http\RedirectResponse;
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
        $contentModelRelations = ContentModelRelation::all(['id', 'content_type', 'model_name']);
        $tableNames = AllowedTableName::availableTables();

        return view('admin.site_settings.create', compact('callContents', 'socialLinks', 'contentModelRelations', 'tableNames'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSiteSettingRequest $request): RedirectResponse
    {
        $siteSetting = SiteSetting::create([
            'site_title' => $request->validated('site_title'),
            'description' => $request->validated('description'),
        ]);

        if ($request->hasFile('site_icon')) {
            $siteSetting->update(['site_icon' => $siteSetting->storeSiteIcon($request->file('site_icon'))]);
        }

        if ($request->hasFile('site_image')) {
            $siteSetting->update(['site_image' => $siteSetting->storeSiteImage($request->file('site_image'))]);
        }

        $this->syncCallContents($request->validated('call_contents', []));
        $this->syncSocialLinks($request->validated('social_links', []));

        return redirect()->route('admin.site-settings.show', $siteSetting)->with('status', 'サイト設定を登録しました。');
    }

    /**
     * Display the specified resource.
     */
    public function show(SiteSetting $siteSetting): View
    {
        $callContents = CallContent::query()->with('contentModelRelation')->orderBy('place')->orderBy('sort_order')->orderBy('id')->get();
        $socialLinks = SocialLink::query()->ordered()->get();

        return view('admin.site_settings.show', compact('siteSetting', 'callContents', 'socialLinks'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SiteSetting $siteSetting): View
    {
        $callContents = CallContent::query()->orderBy('sort_order')->orderBy('id')->get();
        $socialLinks = SocialLink::query()->ordered()->get();
        $contentModelRelations = ContentModelRelation::all(['id', 'content_type', 'model_name']);
        $tableNames = AllowedTableName::availableTables();

        return view('admin.site_settings.edit', compact('siteSetting', 'callContents', 'socialLinks', 'contentModelRelations', 'tableNames'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSiteSettingRequest $request, SiteSetting $siteSetting): RedirectResponse
    {
        $siteSetting->fill([
            'site_title' => $request->validated('site_title'),
            'description' => $request->validated('description'),
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
}
