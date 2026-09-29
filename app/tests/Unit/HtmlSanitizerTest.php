<?php

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HtmlSanitizerTest extends TestCase
{
    /**
     * @return array<string, array{string|null, string|null}>
     */
    public static function htmlProvider(): array
    {
        return [
            'Quill の書式はそのまま残す' => [
                '<p class="ql-align-center"><strong>太字</strong><em>斜体</em><u>下線</u><s>取消</s></p><ol><li data-list="bullet">項目</li></ol>',
                '<p class="ql-align-center"><strong>太字</strong><em>斜体</em><u>下線</u><s>取消</s></p><ol><li data-list="bullet">項目</li></ol>',
            ],
            'スクリプトは中身ごと取り除く' => ['<p>本文<script>alert(1)</script></p>', '<p>本文</p>'],
            'イベント属性と ql- 以外のクラスを取り除く' => ['<p onclick="alert(1)" class="x ql-indent-1" style="color:red">本文</p>', '<p class="ql-indent-1">本文</p>'],
            '許可しないタグは中身を残してタグだけ外す' => ['<div><h1>見出し</h1><img src="x" onerror="alert(1)">本文</div>', '見出し本文'],
            'javascript のリンク先を取り除く' => ['<a href="javascript:alert(1)">リンク</a>', '<a>リンク</a>'],
            '新しいタブのリンクには noopener を付ける' => ['<a href="https://example.com" target="_blank">外部</a>', '<a href="https://example.com" target="_blank" rel="noopener noreferrer">外部</a>'],
            'サイト内のリンクは残す' => ['<a href="/about" target="_self" rel="x">内部</a>', '<a href="/about">内部</a>'],
            '空のエディタは null' => ['<p><br></p>', null],
            'null は null' => [null, null],
        ];
    }

    #[DataProvider('htmlProvider')]
    public function test_clean(?string $html, ?string $expected): void
    {
        $this->assertSame($expected, HtmlSanitizer::clean($html));
    }
}
