<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'biscuit API',
    description: '記事・固定ページ・サイト設定を取得するための公開API。'
)]
#[OA\Server(url: '/api', description: 'API base path')]
abstract class Controller
{
    //
}
