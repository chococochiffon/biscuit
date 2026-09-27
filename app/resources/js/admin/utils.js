/**
 * 管理画面の JS で共通に使う処理(文言の翻訳、CSRF トークン付きの JSON リクエスト)。
 */

/**
 * 画面上の文言を、レイアウトから渡された翻訳(window.adminTranslations。キーは日本語の原文)で返す。
 * 翻訳がなければ原文のまま返す。:name などの置き換え記号は replacements の値で埋める。
 * 使う文言は App\Support\AdminJsTranslations::KEYS にも追加する。
 */
export function t(key, replacements = {}) {
    const text = window.adminTranslations?.[key] ?? key;

    return Object.entries(replacements).reduce((result, [name, value]) => result.replaceAll(`:${name}`, String(value)), text);
}

/**
 * レイアウトの meta タグから CSRF トークンを取得する。
 */
export function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

/**
 * JSON を返す管理画面の API へ、CSRF トークン付きでリクエストする。
 * body がオブジェクトなら JSON に、FormData ならそのまま送る。
 */
export function requestJson(url, { method = 'GET', body } = {}) {
    const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() };
    let payload = body;

    if (body !== undefined && !(body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
        payload = JSON.stringify(body);
    }

    return fetch(url, { method, headers, body: payload });
}
