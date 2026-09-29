<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'biscuit API',
    description: '記事・固定ページ・サイト設定を取得するための公開APIと、chococo のマイページ用の API(ログインが必要。Bearer トークン)。'
)]
#[OA\Server(url: '/api', description: 'API base path')]
#[OA\SecurityScheme(securityScheme: 'bearer', type: 'http', scheme: 'bearer', description: 'POST /auth/login で発行した API トークン')]
abstract class Controller
{
    //
}
