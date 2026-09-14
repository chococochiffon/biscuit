<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdministratorRequest;
use App\Http\Requests\UpdateAdministratorRequest;
use App\Models\Administrator;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdministratorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $administrators = Administrator::query()
            ->orderBy('name')
            ->paginate(20);

        return view('admin.administrators.index', compact('administrators'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin.administrators.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAdministratorRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('password');
        $data['password'] = $request->validated('password');

        Administrator::create($data);

        return redirect()->route('admin.index')->with('status', '管理者を登録しました。');
    }

    /**
     * Display the specified resource.
     */
    public function show(Administrator $administrator): View
    {
        return view('admin.administrators.show', compact('administrator'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Administrator $administrator): View
    {
        return view('admin.administrators.edit', compact('administrator'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAdministratorRequest $request, Administrator $administrator): RedirectResponse
    {
        $data = $request->safe()->except('password');

        if ($password = $request->validated('password')) {
            $data['password'] = $password;
        }

        $administrator->update($data);

        return redirect()->route('admin.index')->with('status', '管理者を更新しました。');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Administrator $administrator): RedirectResponse
    {
        $administrator->delete();

        return redirect()->route('admin.index')->with('status', '管理者を削除しました。');
    }
}
