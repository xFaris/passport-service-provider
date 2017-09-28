<?php

namespace Faris\Passport\Middleware;

use Closure;

class WeChatOauth
{
    public function handle($request, Closure $next)
    {
        $userAgent = $request->header('User-Agent');
        if (empty($userAgent)) {
            return $next($request);
        }

        //判断是否来自微信浏览器
        if (strpos($userAgent, 'MicroMessenger') === false && strpos($userAgent, 'Windows Phone') === false) {
            return $next($request);
        }

        //只有页面GET请求可完成授权
        if ($request->method() != 'GET') {
            return $next($request);
        }

        $weChatAppId = app('passport')->getWeChatAppId();
        if (!empty($weChatAppId)) {
            $openId = app('passport')->getOpenId();
            if (empty($openId)) {
                $redirectUri = $request->fullUrl();

                //验证通过，微信授权
                $oauthUrl = app('passport')->generateWeChatUrl($redirectUri, $weChatAppId);
                return redirect($oauthUrl);
            } else {
                //完成绑定
                if (isset($_COOKIE['hb_token'])) {
                    $token = $_COOKIE['hb_token'];
                    app('passport')->bindOpenId($token, $weChatAppId, $openId);
                }
            }
        }

        return $next($request);
    }
}