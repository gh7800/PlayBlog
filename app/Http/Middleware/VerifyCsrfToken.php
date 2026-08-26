<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{

//    protected $addHttpCookie = true;

    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        // Sanctum EnsureFrontendRequestsAreStateful 会对同源 SPA 请求自动启用 CSRF 校验，
        // 线上前后端同域（www.wangshuai.dpdns.org）时 /api/* 会命中该校验导致 TokenMismatch；
        // API 鉴权走 Bearer token，不依赖 cookie，故豁免。
        'api/*',
    ];
}
