<?php

namespace App\Support;

/**
 * 管理画面の一覧の URL(ページ・検索条件・並び順のクエリ付き)をセッションに覚えておき、
 * 登録・更新・削除・キャンセルのあとに、開いていた一覧のページへ戻すための処理。
 * 覚えるのは RememberAdminListUrl ミドルウェア(一覧の画面を開いたとき)。
 */
class AdminListUrl
{
    /**
     * 覚えた URL を入れるセッションのキー(一覧のパスごとに、最後に開いたクエリ付きの URL)。
     */
    public const SESSION_KEY = 'admin_list_urls';

    /**
     * 一覧の画面のルートか(admin.index と admin.○○.index。Ajax が使う admin.json.* は除く)。
     */
    public static function isListRoute(?string $routeName): bool
    {
        return $routeName !== null
            && ($routeName === 'admin.index' || (str_starts_with($routeName, 'admin.') && str_ends_with($routeName, '.index')))
            && ! str_starts_with($routeName, 'admin.json.');
    }

    /**
     * 一覧の URL を覚える。
     */
    public static function remember(string $path, string $fullUrl): void
    {
        session()->put(self::SESSION_KEY.'.'.self::key($path), $fullUrl);
    }

    /**
     * 戻り先の一覧の URL。最後に開いたときのクエリ付きの URL を覚えていればそれを、なければクエリなしの URL を返す。
     */
    public static function to(string $routeName, mixed $parameters = []): string
    {
        $url = route($routeName, $parameters);
        $remembered = session()->get(self::SESSION_KEY.'.'.self::key((string) parse_url($url, PHP_URL_PATH)));

        return is_string($remembered) ? $remembered : $url;
    }

    /**
     * パスをセッションのキーにする(ドット区切りの入れ子にならないよう、ドットを含まない形にする)。
     */
    private static function key(string $path): string
    {
        return md5(trim($path, '/'));
    }
}
