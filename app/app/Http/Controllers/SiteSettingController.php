<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSiteSettingRequest;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SiteSettingController extends Controller
{
    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin.site_settings.create');
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

        return redirect()->route('admin.site-settings.show', $siteSetting)->with('status', 'サイト設定を登録しました。');
    }

    /**
     * Display the specified resource.
     */
    public function show(SiteSetting $siteSetting): View
    {
        return view('admin.site_settings.show', compact('siteSetting'));
    }
}
