<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSiteSettingRequest;
use App\Http\Requests\UpdateSiteSettingRequest;
use App\Models\CallContent;
use App\Models\ContentModelRelation;
use App\Models\SiteSetting;
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
        $contentModelRelations = ContentModelRelation::all(['id', 'content_type', 'model_name']);
        $tableNames = AllowedTableName::availableTables();

        return view('admin.site_settings.create', compact('callContents', 'contentModelRelations', 'tableNames'));
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

        return redirect()->route('admin.site-settings.show', $siteSetting)->with('status', 'サイト設定を登録しました。');
    }

    /**
     * Display the specified resource.
     */
    public function show(SiteSetting $siteSetting): View
    {
        $callContents = CallContent::query()->with('contentModelRelation')->orderBy('id')->get();

        return view('admin.site_settings.show', compact('siteSetting', 'callContents'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SiteSetting $siteSetting): View
    {
        $callContents = CallContent::query()->orderBy('id')->get();
        $contentModelRelations = ContentModelRelation::all(['id', 'content_type', 'model_name']);
        $tableNames = AllowedTableName::availableTables();

        return view('admin.site_settings.edit', compact('siteSetting', 'callContents', 'contentModelRelations', 'tableNames'));
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

        return redirect()->route('admin.site-settings.show', $siteSetting)->with('status', 'サイト設定を更新しました。');
    }

    /**
     * フォームから送信された呼び出しコンテンツ(call_contents)の内容にデータベースを同期する。
     * 送信された行はid有無で作成/更新し、送信されなかった既存行は削除する。
     *
     * @param  array<int, array{id?: int|string|null, call_type: int|string, call_name: string, content_model_relation_id: int|string, view_count: int|string, place: int|string}>  $rows
     */
    private function syncCallContents(array $rows): void
    {
        $submittedIds = collect($rows)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

        CallContent::query()->whereNotIn('id', $submittedIds)->delete();

        foreach ($rows as $row) {
            $attributes = [
                'call_type' => $row['call_type'],
                'call_name' => $row['call_name'],
                'content_model_relation_id' => $row['content_model_relation_id'],
                'view_count' => $row['view_count'],
                'place' => $row['place'],
            ];

            if (! empty($row['id'])) {
                CallContent::query()->whereKey($row['id'])->update($attributes);
            } else {
                CallContent::create($attributes);
            }
        }
    }
}
