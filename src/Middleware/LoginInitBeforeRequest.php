<?php

namespace Faris\Passport\Middleware;

use Closure;

class LoginInitBeforeRequest
{
    public function handle($request, Closure $next)
    {
        if (isset($_COOKIE['hb_token'])) {
            $token = $_COOKIE['hb_token'];
            app('passport')->checkUserLogin($token);
        }

        $response = $next($request);
        return $response;
    }
}