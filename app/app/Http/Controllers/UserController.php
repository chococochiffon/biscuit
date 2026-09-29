<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SavesUserProfile;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Models\UserDetail;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserController extends Controller
{
    use SavesUserProfile;

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $users = User::query()
            ->with('detail')
            ->orderBy('name')
            ->paginate(config('limits.admin_per_page'));

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
        DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'password' => $request->validated('password'),
            ]);

            $detail = $user->detail()->create($this->userDetailAttributes($request));

            $this->storeUserImage($request, $detail);

            $skills = $this->syncSkills($detail, $request->validated('user_detail.skills', []));

            AuditLogger::created($user, ['skills' => $skills->summary()], $this->auditDetail($detail->fresh()));
        });

        return redirect()->route('admin.users.index')->with('status', __('ユーザーを登録しました。'));
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
        DB::transaction(function () use ($request, $user) {
            $before = AuditLogger::snapshot($user, $this->auditDetail($user->detail));

            $data = [
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
            ];

            if ($password = $request->validated('password')) {
                $data['password'] = $password;
            }

            $user->update($data);

            $detail = $user->detail()->updateOrCreate([], $this->userDetailAttributes($request));

            $this->storeUserImage($request, $detail);

            $skills = $this->syncSkills($detail, $request->validated('user_detail.skills', []));

            // パスワードは値を残さず、変更したことだけを残す
            AuditLogger::updated(
                $user,
                $before,
                ['skills' => $skills->summary(), 'password_changed' => isset($data['password'])],
                extra: $this->auditDetail($detail->fresh()),
            );
        });

        return redirect()->route('admin.users.index')->with('status', __('ユーザーを更新しました。'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user): RedirectResponse
    {
        AuditLogger::deleteWithLog($user);

        return redirect()->route('admin.users.index')->with('status', __('ユーザーを削除しました。'));
    }

    /**
     * アイコン画像が送信されていれば、指定された切り抜き範囲(1つでも未指定なら中央)で保存してユーザー詳細に設定する。
     */
    private function storeUserImage(StoreUserRequest|UpdateUserRequest $request, UserDetail $detail): void
    {
        if (! $request->hasFile('user_detail.user_image')) {
            return;
        }

        $crop = collect(['x', 'y', 'width', 'height'])
            ->mapWithKeys(fn (string $key) => [$key => $request->validated("user_detail.user_image_crop.{$key}")]);

        $detail->update([
            'user_image' => $detail->storeUserImage(
                $request->file('user_detail.user_image'),
                $crop->contains(fn ($value) => blank($value)) ? null : $crop->map(fn ($value) => (float) $value)->all()
            ),
        ]);
    }
}
