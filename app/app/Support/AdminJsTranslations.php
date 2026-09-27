<?php

namespace App\Support;

/**
 * 管理画面の JS(resources/js/admin.js と resources/js/admin/*.js)で表示する文言。
 * レイアウトで現在の言語に翻訳して window.adminTranslations に渡し、JS では t('日本語の原文') で参照する。
 * キーは lang/en.json と同じく日本語の原文で、:name などの置き換え記号は JS 側で埋める。
 */
class AdminJsTranslations
{
    /**
     * JS で使う文言のキー(管理画面の JS に t('...') を足したらここにも追加する)。
     *
     * @var list<string>
     */
    public const KEYS = [
        '登録',
        '更新',
        '編集',
        '削除',
        'タグを削除',
        '「:name」を削除してよろしいですか?',
        'タグの削除に失敗しました。',
        'タグの保存に失敗しました。',
        '画像のアップロードに失敗しました。',
        '選択した表示箇所ではこの呼び出し方は選択できなくなりました。呼び出し方を選び直してください。',
        '選択した表示箇所・呼び出し方ではこのデータ種別は選択できなくなりました。データ種別を選び直してください。',
        'このデータ種別の紐付けは使用されているため削除できません。',
        'データ種別の紐付けの削除に失敗しました。',
        'データ種別の紐付けの保存に失敗しました。',
        '記事を選択してください。',
    ];

    /**
     * 現在の言語に翻訳した文言(キー → 翻訳)。
     *
     * @return array<string, string>
     */
    public static function translated(): array
    {
        return collect(self::KEYS)->mapWithKeys(fn (string $key) => [$key => __($key)])->all();
    }
}
