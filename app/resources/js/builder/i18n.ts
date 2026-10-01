// エディタの文言。画面(layouts/builder.blade.php)が現在の言語に翻訳して window.builderTranslations に渡す
// (キーは日本語の原文。t() に渡す文言は App\Support\PageBuilderJsTranslations::KEYS に登録する)

declare global {
  interface Window {
    builderTranslations?: Record<string, string>
  }
}

/**
 * 文言を現在の言語で返す。:name などの置き換え記号は replacements で埋める。
 */
export function t(key: string, replacements: Record<string, string | number> = {}): string {
  let text = window.builderTranslations?.[key] ?? key

  for (const [name, value] of Object.entries(replacements)) {
    text = text.replaceAll(`:${name}`, String(value))
  }

  return text
}
