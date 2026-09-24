<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Models\UserDetail;
use App\Models\UserSkill;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $users = User::query()
            ->with('detail')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin.users.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
        ]);

        $detail = $user->detail()->create($this->userDetailAttributes($request));

        if ($request->hasFile('user_detail.user_image')) {
            $detail->update(['user_image' => $detail->storeUserImage($request->file('user_detail.user_image'))]);
        }

        $this->syncSkills($detail, $request->validated('user_detail.skills', []));

        return redirect()->route('admin.users.index')->with('status', 'ユーザーを登録しました。');
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user): View
    {
        $user->load('detail.skills');

        return view('admin.users.show', compact('user'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user): View
    {
        $user->load('detail.skills');

        return view('admin.users.edit', compact('user'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = [
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
        ];

        if ($password = $request->validated('password')) {
            $data['password'] = $password;
        }

        $user->update($data);

        $detail = $user->detail()->updateOrCreate([], $this->userDetailAttributes($request));

        if ($request->hasFile('user_detail.user_image')) {
            $detail->update(['user_image' => $detail->storeUserImage($request->file('user_detail.user_image'))]);
        }

        $this->syncSkills($detail, $request->validated('user_detail.skills', []));

        return redirect()->route('admin.users.index')->with('status', 'ユーザーを更新しました。');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user): RedirectResponse
    {
        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'ユーザーを削除しました。');
    }

    /**
     * リクエストからuser_detailの保存用属性を組み立てる(画像は別途保存する)。
     *
     * @return array<string, mixed>
     */
    private function userDetailAttributes(StoreUserRequest|UpdateUserRequest $request): array
    {
        return [
            'first_name' => $request->validated('user_detail.first_name'),
            'family_name' => $request->validated('user_detail.family_name'),
            'nick_name' => $request->validated('user_detail.nick_name'),
            'birthday' => $request->validated('user_detail.birthday'),
            'comment' => $request->validated('user_detail.comment'),
            'view_flag' => $request->boolean('user_detail.view_flag'),
            'name_settings' => $request->validated('user_detail.name_settings'),
        ];
    }

    /**
     * フォームから送信されたスキル(user_detail.skills)の内容に、ユーザー詳細のスキルを同期する。
     * 送信された行はid有無で作成/更新し、送信されなかった既存行は削除する。
     * 並び順(sort_order)は画面上の行の順(未送信の場合は送信順)で保存する。
     *
     * @param  array<int, array{id?: int|string|null, name: string, level: int|string, sort_order?: int|string|null}>  $rows
     */
    private function syncSkills(UserDetail $detail, array $rows): void
    {
        $submittedIds = collect($rows)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

        UserSkill::query()->where('user_detail_id', $detail->id)->whereNotIn('id', $submittedIds)->delete();

        foreach (array_values($rows) as $index => $row) {
            $attributes = [
                'name' => $row['name'],
                'level' => $row['level'],
                'sort_order' => $row['sort_order'] ?? $index,
            ];

            if (! empty($row['id'])) {
                UserSkill::query()->where('user_detail_id', $detail->id)->whereKey($row['id'])->update($attributes);
            } else {
                $detail->skills()->create($attributes);
            }
        }
    }
}
