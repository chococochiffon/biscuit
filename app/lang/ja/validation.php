<?php

return [

    /*
    |--------------------------------------------------------------------------
    | バリデーションの言語設定
    |--------------------------------------------------------------------------
    |
    | Laravel 本体の英語の定義(vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php)を
    | 日本語に訳したもの。:attribute は下の attributes の項目名(未定義ならキー名)に置き換わる。
    |
    */

    'accepted' => ':attributeを承認してください。',
    'accepted_if' => ':otherが:valueの場合、:attributeを承認してください。',
    'active_url' => ':attributeには有効なURLを入力してください。',
    'after' => ':attributeには:dateより後の日付を入力してください。',
    'after_or_equal' => ':attributeには:date以降の日付を入力してください。',
    'alpha' => ':attributeには英字のみ入力できます。',
    'alpha_dash' => ':attributeには英数字・ハイフン・アンダースコアのみ入力できます。',
    'alpha_num' => ':attributeには英数字のみ入力できます。',
    'any_of' => ':attributeの値が正しくありません。',
    'array' => ':attributeは配列で指定してください。',
    'array_keys' => ':attributeには次のキーのみ指定できます: :values',
    'ascii' => ':attributeには半角英数字と記号のみ入力できます。',
    'before' => ':attributeには:dateより前の日付を入力してください。',
    'before_or_equal' => ':attributeには:date以前の日付を入力してください。',
    'between' => [
        'array' => ':attributeは:min件から:max件で指定してください。',
        'file' => ':attributeは:minKBから:maxKBのファイルを指定してください。',
        'numeric' => ':attributeには:minから:maxの値を入力してください。',
        'string' => ':attributeは:min文字から:max文字で入力してください。',
    ],
    'boolean' => ':attributeには「はい」か「いいえ」を指定してください。',
    'can' => ':attributeに許可されていない値が含まれています。',
    'confirmed' => ':attributeが確認用の入力と一致しません。',
    'contains' => ':attributeに必要な値が含まれていません。',
    'current_password' => 'パスワードが正しくありません。',
    'date' => ':attributeには有効な日付を入力してください。',
    'date_equals' => ':attributeには:dateと同じ日付を入力してください。',
    'date_format' => ':attributeは:formatの形式で入力してください。',
    'decimal' => ':attributeは小数点以下:decimal桁で入力してください。',
    'declined' => ':attributeを拒否してください。',
    'declined_if' => ':otherが:valueの場合、:attributeを拒否してください。',
    'different' => ':attributeと:otherには異なる値を入力してください。',
    'digits' => ':attributeは:digits桁で入力してください。',
    'digits_between' => ':attributeは:min桁から:max桁で入力してください。',
    'dimensions' => ':attributeの画像サイズが正しくありません。',
    'distinct' => ':attributeの値が重複しています。',
    'doesnt_contain' => ':attributeには次の値を含められません: :values',
    'doesnt_end_with' => ':attributeは次のいずれかで終わらないようにしてください: :values',
    'doesnt_start_with' => ':attributeは次のいずれかで始まらないようにしてください: :values',
    'email' => ':attributeには有効なメールアドレスを入力してください。',
    'encoding' => ':attributeは:encodingでエンコードしてください。',
    'ends_with' => ':attributeは次のいずれかで終わるようにしてください: :values',
    'enum' => '選択された:attributeは正しくありません。',
    'exists' => '選択された:attributeは正しくありません。',
    'extensions' => ':attributeには次のいずれかの拡張子のファイルを指定してください: :values',
    'file' => ':attributeにはファイルを指定してください。',
    'filled' => ':attributeを入力してください。',
    'gt' => [
        'array' => ':attributeは:value件より多く指定してください。',
        'file' => ':attributeには:valueKBより大きいファイルを指定してください。',
        'numeric' => ':attributeには:valueより大きい値を入力してください。',
        'string' => ':attributeは:value文字より多く入力してください。',
    ],
    'gte' => [
        'array' => ':attributeは:value件以上指定してください。',
        'file' => ':attributeには:valueKB以上のファイルを指定してください。',
        'numeric' => ':attributeには:value以上の値を入力してください。',
        'string' => ':attributeは:value文字以上で入力してください。',
    ],
    'hex_color' => ':attributeには有効な16進数のカラーコードを入力してください。',
    'image' => ':attributeには画像を指定してください。',
    'in' => '選択された:attributeは正しくありません。',
    'in_array' => ':attributeは:otherに含まれる値を指定してください。',
    'in_array_keys' => ':attributeには次のキーのうち少なくとも1つを含めてください: :values',
    'integer' => ':attributeには整数を入力してください。',
    'ip' => ':attributeには有効なIPアドレスを入力してください。',
    'json' => ':attributeには有効なJSON文字列を入力してください。',
    'list' => ':attributeはリストで指定してください。',
    'lowercase' => ':attributeは小文字で入力してください。',
    'lt' => [
        'array' => ':attributeは:value件より少なく指定してください。',
        'file' => ':attributeには:valueKBより小さいファイルを指定してください。',
        'numeric' => ':attributeには:valueより小さい値を入力してください。',
        'string' => ':attributeは:value文字より少なく入力してください。',
    ],
    'lte' => [
        'array' => ':attributeは:value件以下で指定してください。',
        'file' => ':attributeには:valueKB以下のファイルを指定してください。',
        'numeric' => ':attributeには:value以下の値を入力してください。',
        'string' => ':attributeは:value文字以下で入力してください。',
    ],
    'mac_address' => ':attributeには有効なMACアドレスを入力してください。',
    'max' => [
        'array' => ':attributeは:max件以下で指定してください。',
        'file' => ':attributeには:maxKB以下のファイルを指定してください。',
        'numeric' => ':attributeには:max以下の値を入力してください。',
        'string' => ':attributeは:max文字以下で入力してください。',
    ],
    'max_digits' => ':attributeは:max桁以下で入力してください。',
    'mimes' => ':attributeには次の形式のファイルを指定してください: :values',
    'mimetypes' => ':attributeには次の形式のファイルを指定してください: :values',
    'min' => [
        'array' => ':attributeは:min件以上指定してください。',
        'file' => ':attributeには:minKB以上のファイルを指定してください。',
        'numeric' => ':attributeには:min以上の値を入力してください。',
        'string' => ':attributeは:min文字以上で入力してください。',
    ],
    'min_digits' => ':attributeは:min桁以上で入力してください。',
    'missing' => ':attributeは指定できません。',
    'missing_if' => ':otherが:valueの場合、:attributeは指定できません。',
    'missing_unless' => ':otherが:valueでない場合、:attributeは指定できません。',
    'missing_with' => ':valuesを指定した場合、:attributeは指定できません。',
    'missing_with_all' => ':valuesをすべて指定した場合、:attributeは指定できません。',
    'multiple_of' => ':attributeには:valueの倍数を入力してください。',
    'not_in' => '選択された:attributeは正しくありません。',
    'not_regex' => ':attributeの形式が正しくありません。',
    'numeric' => ':attributeには数値を入力してください。',
    'password' => [
        'letters' => ':attributeには英字を1文字以上含めてください。',
        'mixed' => ':attributeには大文字と小文字をそれぞれ1文字以上含めてください。',
        'numbers' => ':attributeには数字を1文字以上含めてください。',
        'symbols' => ':attributeには記号を1文字以上含めてください。',
        'uncompromised' => '指定された:attributeは過去に漏洩したことがあります。別の:attributeを指定してください。',
    ],
    'present' => ':attributeの項目を含めてください。',
    'present_if' => ':otherが:valueの場合、:attributeの項目を含めてください。',
    'present_unless' => ':otherが:valueでない場合、:attributeの項目を含めてください。',
    'present_with' => ':valuesを指定した場合、:attributeの項目を含めてください。',
    'present_with_all' => ':valuesをすべて指定した場合、:attributeの項目を含めてください。',
    'prohibited' => ':attributeは入力できません。',
    'prohibited_if' => ':otherが:valueの場合、:attributeは入力できません。',
    'prohibited_if_accepted' => ':otherを承認した場合、:attributeは入力できません。',
    'prohibited_if_declined' => ':otherを拒否した場合、:attributeは入力できません。',
    'prohibited_unless' => ':otherが:valuesに含まれない場合、:attributeは入力できません。',
    'prohibits' => ':attributeを入力した場合、:otherは入力できません。',
    'regex' => ':attributeの形式が正しくありません。',
    'required' => ':attributeを入力してください。',
    'required_array_keys' => ':attributeには次の項目を含めてください: :values',
    'required_if' => ':otherが:valueの場合、:attributeを入力してください。',
    'required_if_accepted' => ':otherを承認した場合、:attributeを入力してください。',
    'required_if_declined' => ':otherを拒否した場合、:attributeを入力してください。',
    'required_unless' => ':otherが:valuesに含まれない場合、:attributeを入力してください。',
    'required_with' => ':valuesを入力した場合、:attributeを入力してください。',
    'required_with_all' => ':valuesをすべて入力した場合、:attributeを入力してください。',
    'required_without' => ':valuesを入力しない場合、:attributeを入力してください。',
    'required_without_all' => ':valuesをどれも入力しない場合、:attributeを入力してください。',
    'same' => ':attributeと:otherが一致しません。',
    'size' => [
        'array' => ':attributeは:size件で指定してください。',
        'file' => ':attributeには:sizeKBのファイルを指定してください。',
        'numeric' => ':attributeには:sizeを入力してください。',
        'string' => ':attributeは:size文字で入力してください。',
    ],
    'starts_with' => ':attributeは次のいずれかで始まるようにしてください: :values',
    'string' => ':attributeには文字列を入力してください。',
    'timezone' => ':attributeには有効なタイムゾーンを指定してください。',
    'unique' => 'その:attributeはすでに使われています。',
    'uploaded' => ':attributeのアップロードに失敗しました。',
    'uppercase' => ':attributeは大文字で入力してください。',
    'url' => ':attributeには有効なURLを入力してください。',
    'ulid' => ':attributeには有効なULIDを入力してください。',
    'uuid' => ':attributeには有効なUUIDを入力してください。',

    /*
    |--------------------------------------------------------------------------
    | 項目ごとのメッセージ
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 項目名
    |--------------------------------------------------------------------------
    |
    | エラーメッセージの :attribute に表示する項目名(管理画面のラベルと揃える)。
    | 繰り返し入力の行は「*」で行番号を問わずに指定する。
    |
    */

    'attributes' => [
        // 共通
        'title' => 'タイトル',
        'name' => '名前',
        'email' => 'メールアドレス',
        'password' => 'パスワード',
        'description' => '説明',
        'type' => '形式',
        'parent_path' => '親パス',
        'slug' => 'スラッグ',
        'publication_start_datetime' => '公開開始',
        'publication_end_datetime' => '公開終了',

        // 記事・タグ
        'content' => '本文',
        'thumbnail' => 'サムネイル画像',
        'approval' => '公開ステータス',
        'tags' => 'タグ',
        'tags.*' => 'タグ',
        'tag_name' => 'タグ名',
        'article_ids' => '記事',
        'image' => '画像',

        // 固定ページ
        'short_sentences' => '概要',
        'header_image' => 'ヘッダー画像',
        'top_page_view' => 'Topページへ表示する',
        'link_list_view' => 'リンクリストへ表示する',
        'details' => '詳細',
        'details.*.sub_title' => '詳細の小見出し',
        'details.*.contents' => '詳細の本文',
        'order' => '並び順',

        // サイト設定
        'site_title' => 'サイトタイトル',
        'front_url' => 'フロントのURL',
        'api_url' => 'APIのURL',
        'site_icon' => 'サイトアイコン',
        'site_image' => 'サイト画像',
        'social_links' => 'SNSリンク',
        'social_links.*.service' => 'SNSリンクのサービス',
        'social_links.*.name' => 'SNSリンクの表示名',
        'social_links.*.url' => 'SNSリンクのURL',
        'top_slider_images' => 'トップスライダー画像',
        'top_slider_images.*.image' => 'トップスライダー画像',
        'top_slider_images.*.url' => 'トップスライダー画像のリンク先URL',
        'call_contents' => 'API設定',
        'call_contents.*.call_name' => 'API設定の呼び出し名',
        'call_contents.*.call_type' => 'API設定の呼び出し方',
        'call_contents.*.title' => 'API設定の見出し',
        'call_contents.*.subtitle' => 'API設定の小見出し',
        'call_contents.*.content_model_relation_id' => 'API設定のデータ種別',
        'call_contents.*.view_count' => 'API設定の表示件数',
        'call_contents.*.place' => 'API設定の表示箇所',

        // データ種別紐付け
        'content_type' => 'コンテンツ種別',
        'model_name' => 'モデル名',
        'table_name' => 'テーブル名',

        // 管理者・ユーザー
        'role' => '権限',
        'user_detail' => 'ユーザー詳細',
        'user_detail.first_name' => '名',
        'user_detail.family_name' => '姓',
        'user_detail.nick_name' => 'ニックネーム',
        'user_detail.birthday' => '生年月日',
        'user_detail.user_image' => 'アイコン画像',
        'user_detail.comment' => 'コメント',
        'user_detail.view_flag' => '表示する',
        'user_detail.name_settings' => '名前の表示設定',
        'user_detail.skills.*.name' => 'スキル名',
        'user_detail.skills.*.level' => 'スキルの習熟度',
        'user_detail.skills.*.id' => 'スキル',

        // Q&A(入れ子の質問・回答の項目名は ValidatesQuestionAnswer::attributes() で指定する)
        'short_question_text' => '質問(簡易版)',
        'short_answer_text' => '回答(簡易版)',
    ],

];
