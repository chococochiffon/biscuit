<?php

namespace App\Support\Builder;

/**
 * 動画のブロックの URL(YouTube・Vimeo)を解析し、埋め込み用の URL を組み立てる。
 * 入力された URL をそのまま iframe に入れず、動画の ID だけを取り出して決まった形の URL にする(ほかのサイト・スクリプトを読み込ませない)。
 * 管理画面のエディタ(resources/js/builder/video.ts)・chococo も同じ形だけを受け付ける。
 */
final class VideoUrl
{
    /**
     * YouTube の動画の URL(watch?v=・youtu.be・shorts・embed)。ID は 11 文字。
     */
    private const YOUTUBE_PATTERN = '#\Ahttps://(?:(?:www\.|m\.)?youtube\.com/(?:watch\?(?:[^\s\#]*&)?v=|shorts/|embed/)|youtu\.be/)([A-Za-z0-9_-]{11})(?:[?&\#][^\s]*)?\z#';

    /**
     * Vimeo の動画の URL(vimeo.com/数字・player.vimeo.com/video/数字)。
     */
    private const VIMEO_PATTERN = '#\Ahttps://(?:www\.)?(?:vimeo\.com|player\.vimeo\.com/video)/(\d{1,12})(?:[/?\#][^\s]*)?\z#';

    /**
     * 埋め込み用の URL。対応していない URL なら null。
     */
    public static function embedUrl(string $url): ?string
    {
        if (strlen($url) > 2048) {
            return null;
        }

        if (preg_match(self::YOUTUBE_PATTERN, $url, $matches) === 1) {
            return 'https://www.youtube-nocookie.com/embed/'.$matches[1];
        }

        if (preg_match(self::VIMEO_PATTERN, $url, $matches) === 1) {
            return 'https://player.vimeo.com/video/'.$matches[1];
        }

        return null;
    }
}
