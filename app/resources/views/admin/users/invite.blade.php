@extends('layouts.admin')

@section('title', __('ユーザーの招待'))

@section('content')
    <h1 class="h5 mb-4 mx-auto" style="max-width: 36rem;">{{ __('ユーザーの招待') }}</h1>

    <form method="POST" action="{{ route('admin.users.invite.store') }}" class="card card-body mx-auto mb-3" style="max-width: 36rem;">
        @csrf

        @include('admin.partials._form_errors')

        <p class="small text-body-secondary">
            {{ __('入力したメールアドレスに招待のメールを送ります。メールのリンクからプロフィールとパスワードを登録すると、マイページにログインできるようになります。') }}
            {{ __('アカウント名は、メールアドレスの @ の前にします(登録のときに変えられます)。') }}
            {{ __('リンクの有効期限は :hours 時間です。', ['hours' => round(config('auth.invitations.expire') / 60, 1)]) }}
        </p>

        <div class="mb-3">
            <label for="email" class="form-label">{{ __('メールアドレス') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required class="form-control">
        </div>

        <div class="mb-4 form-check">
            <input
                id="skip_approval"
                type="checkbox"
                name="skip_approval"
                value="1"
                class="form-check-input"
                @checked(old('skip_approval', false))
            >
            <label for="skip_approval" class="form-check-label">{{ __('記事とギャラリーを承認なしで公開する') }}</label>
            <div class="form-text">{{ __('チェックすると、このユーザーがマイページから投稿した記事とギャラリーの画像は、管理者の承認を待たずに公開されます。') }}</div>
        </div>

        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">{{ __('招待する') }}</button>
            <a href="{{ route('admin.users.index') }}" class="text-secondary">{{ __('キャンセル') }}</a>
        </div>
    </form>
@endsection
