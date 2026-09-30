<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\StoreUserInvitationRequest;
use App\Models\Administrator;
use App\Models\User;
use App\Models\UserInvitation;
use App\Notifications\UserInvitationNotification;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * 管理者からユーザーへの招待。メールアドレスを入れて招待すると、アカウント名をメールアドレスの @ の前にした
 * 無効なユーザー(active_flag = false)を作り、招待のメールを送る。ユーザーがメールのリンク(公開側 chococo の /invitation)で
 * プロフィールとパスワードを登録すると有効になる(API\InvitationController)。リンクの期限が切れたら、管理者がユーザー一覧から再送する。
 */
class UserInvitationController extends Controller
{
    /**
     * 招待の画面を表示する。
     */
    public function create(): View
    {
        return view('admin.users.invite');
    }

    /**
     * ユーザーを招待する(無効なユーザーを作り、招待のメールを送る)。
     * パスワードはユーザーが招待のリンクで決めるため、それまでは誰も知らないランダムな値にしておく。
     */
    public function store(StoreUserInvitationRequest $request): RedirectResponse
    {
        [$user, $token] = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => User::accountNameFromEmail($request->validated('email')),
                'email' => $request->validated('email'),
                'password' => Str::random(64),
                'skip_approval' => $request->boolean('skip_approval'),
                'active_flag' => false,
            ]);

            $token = UserInvitation::issue($user, $this->administrator());
            AuditLogger::created($user, ['invited' => true]);

            return [$user, $token];
        });

        $user->notify(new UserInvitationNotification($token));

        return redirect()->route('admin.users.index')->with('status', __(':email に招待のメールを送りました。', ['email' => $user->email]));
    }

    /**
     * まだ招待を受けていない(無効な)ユーザーに、招待のメールを送り直す。それまでの招待のリンクは使えなくなる。
     */
    public function resend(User $user): RedirectResponse
    {
        abort_if($user->active_flag, 404);

        $token = DB::transaction(function () use ($user) {
            $token = UserInvitation::issue($user, $this->administrator());
            AuditLogger::record(AuditAction::Invited, $user, metadata: ['resent' => true]);

            return $token;
        });

        $user->notify(new UserInvitationNotification($token));

        return redirect()->route('admin.users.index')->with('status', __(':email に招待のメールを送り直しました。', ['email' => $user->email]));
    }

    private function administrator(): ?Administrator
    {
        return Auth::guard('admin')->user();
    }
}
