<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * リッチテキストエディタ(Quill)で入力した HTML から、許可したタグ・属性以外を取り除く。
 * 許可しないタグは中身を残してタグだけ外し、スクリプトなど中身ごと危険なタグは中身ごと取り除く。
 * clean() はレイアウトの自由テキスト用、cleanArticle() はユーザー(chococo のマイページ)が書いた記事の本文用。
 */
class HtmlSanitizer
{
    /**
     * 残すタグ(Quill の太字・斜体・下線・取り消し線・リンク・リストで使うもの)。
     *
     * @var list<string>
     */
    private const ALLOWED_TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'a', 'ol', 'ul', 'li', 'span'];

    /**
     * 記事の本文だけで、ALLOWED_TAGS に加えて残すタグ(Quill の見出しと画像)。
     *
     * @var list<string>
     */
    private const ARTICLE_TAGS = ['h1', 'h2', 'h3', 'img'];

    /**
     * 中身ごと取り除くタグ。
     *
     * @var list<string>
     */
    private const REMOVED_TAGS = ['script', 'style', 'iframe', 'object', 'embed', 'template', 'noscript', 'svg', 'math', 'form', 'input', 'button', 'select', 'textarea'];

    /**
     * タグごとに残す属性(class はすべてのタグで Quill の書式用(ql-)のものだけ残す)。
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href', 'target', 'rel'],
        'li' => ['data-list'],
        'img' => ['src', 'alt'],
    ];

    /**
     * HTML を無害化して返す。文字が残らない(空のエディタの <p><br></p> など)場合は null を返す。
     */
    public static function clean(?string $html): ?string
    {
        return self::sanitize($html, self::ALLOWED_TAGS, null);
    }

    /**
     * ユーザーが書いた記事の本文を無害化して返す。見出しと、$imageUrlPrefix で始まる画像(本文用にアップロードした画像)も残す。
     * それ以外の画像は取り除く(外部の画像を読み込ませないため)。
     */
    public static function cleanArticle(?string $html, string $imageUrlPrefix): ?string
    {
        return self::sanitize($html, [...self::ALLOWED_TAGS, ...self::ARTICLE_TAGS], $imageUrlPrefix);
    }

    /**
     * @param  list<string>  $allowedTags
     * @param  string|null  $imageUrlPrefix  画像を残す場合の src の先頭
     */
    private static function sanitize(?string $html, array $allowedTags, ?string $imageUrlPrefix): ?string
    {
        if ($html === null || (trim(strip_tags($html)) === '' && ($imageUrlPrefix === null || ! str_contains($html, '<img')))) {
            return null;
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        // 文字コードを UTF-8 と明示し、断片をひとつの要素に包んで読み込む
        $document->loadHTML('<?xml encoding="UTF-8"?><html><body><div>'.$html.'</div></body></html>');
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $wrapper = $document->getElementsByTagName('div')->item(0);

        if ($wrapper === null) {
            return null;
        }

        self::sanitizeChildren($wrapper, $allowedTags, $imageUrlPrefix);

        $cleaned = '';

        foreach ($wrapper->childNodes as $child) {
            $cleaned .= $document->saveHTML($child);
        }

        return trim(strip_tags($cleaned)) === '' && ! str_contains($cleaned, '<img') ? null : $cleaned;
    }

    /**
     * 子孫の要素を、許可したタグ・属性だけになるよう書き換える。
     */
    /**
     * @param  list<string>  $allowedTags
     */
    private static function sanitizeChildren(DOMNode $parent, array $allowedTags, ?string $imageUrlPrefix): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof DOMText) {
                continue;
            }

            if (! $node instanceof DOMElement) {
                // コメントなど
                $parent->removeChild($node);

                continue;
            }

            $tag = strtolower($node->tagName);

            if (in_array($tag, self::REMOVED_TAGS, true)) {
                $parent->removeChild($node);

                continue;
            }

            self::sanitizeChildren($node, $allowedTags, $imageUrlPrefix);

            if (! in_array($tag, $allowedTags, true)) {
                // タグだけ外して中身を残す
                while ($node->firstChild !== null) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);

                continue;
            }

            if ($tag === 'img' && ! str_starts_with(trim($node->getAttribute('src')), (string) $imageUrlPrefix)) {
                $parent->removeChild($node);

                continue;
            }

            self::sanitizeAttributes($node, $tag);
        }
    }

    /**
     * 要素の属性を、許可したものだけにする。リンク先は http(s)・mailto・サイト内(/・#)だけ残す。
     */
    private static function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);

            if ($name === 'class') {
                $classes = array_filter(preg_split('/\s+/', $attribute->value) ?: [], fn (string $class) => str_starts_with($class, 'ql-'));
                $classes === [] ? $element->removeAttribute($attribute->name) : $element->setAttribute('class', implode(' ', $classes));

                continue;
            }

            if (! in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->name);
            }
        }

        if ($tag !== 'a') {
            return;
        }

        if (! preg_match('#^(https?:|mailto:|/|\#)#i', trim($element->getAttribute('href')))) {
            $element->removeAttribute('href');
        }

        if ($element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        } else {
            $element->removeAttribute('target');
            $element->removeAttribute('rel');
        }
    }
}
