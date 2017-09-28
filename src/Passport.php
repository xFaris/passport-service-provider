<?php

namespace Faris\Passport;

use Illuminate\Config\Repository;
use GuzzleHttp\Client;
use Exception;

class Passport
{
    protected $config;
    protected $client;
    protected $requestTimeout;
    protected $connectTimeout;
    protected $bindHost;
    protected $baseUri;
    protected $secret;

    protected $userId;
    protected $userInfo;
    protected $openId;
    protected $product;

    public function __construct(Repository $config)
    {
        $this->config = $config;

        //basic info
        if ($this->config->has('passport.product')) {
            $this->product = $this->config->get('passport.product');
        } else {
            $this->product = 'mp';
        }
        if ($this->config->has('passport.base_uri')) {
            $this->baseUri = $this->config->get('passport.base_uri');
        } else {
            $this->baseUri = "https://passport.faris.com";
        }
        if ($this->config->has('passport.bind_host')) {
            $this->bindHost = $this->config->get('passport.bind_host');
        } else {
            $this->bindHost = "10.3.250.91";
        }
        if ($this->config->has('passport.secret')) {
            $this->secret = $this->config->get('passport.secret');
        } else {
            $this->secret = "";
        }
        //timeout
        if ($this->config->has('passport.request_timeout')) {
            $this->requestTimeout = $this->config->get('passport.request_timeout');
        } else {
            $this->requestTimeout = 0;
        }
        if ($this->config->has('passport.connect_timeout')) {
            $this->connectTimeout = $this->config->get('passport.connect_timeout');
        } else {
            $this->connectTimeout = 0;
        }

        $this->client = new Client([
            'base_uri' => $this->baseUri,
            'timeout'  => $this->requestTimeout,
            'connect_timeout' => $this->connectTimeout,
            'http_errors' => true,
            'headers'  => [
                'host' => $this->bindHost,
            ],
        ]);

        $this->userId = 0;
        $this->openId = '';
        $this->userInfo = [];
    }

    public function isLogin()
    {
        return empty($this->userId) ? false : true;
    }

    public function getUserId()
    {
        return $this->userId;
    }

    public function getWeChatAppId()
    {
        if ($this->config->has('passport.wechat_appid')) {
            return $this->config->get('passport.wechat_appid');
        } else {
            return '';
        }
    }

    public function getOpenId()
    {
        if (!empty($this->openId)) {
            return $this->openId;
        }

        $weChatAppId = $this->getWeChatAppId();
        if (!empty($weChatAppId)) {
            $cookieName = "openid_" . $weChatAppId;

            //判断是否已获取openid
            if (isset($_COOKIE[$cookieName])) {
                $this->openId = $_COOKIE[$cookieName];
            }
        }
        return $this->openId;
    }

    public function checkUserLogin($token)
    {
        $checkTokenUri = "/v1/server/checkToken";
        if ($this->config->has('passport.uri.server.check_token')) {
            $checkTokenUri = $this->config->get('passport.uri.server.check_token');
        }

        $formParams = [
            'hb_token' => $token,
            'product' => $this->product,
            'nonce' => time(),
        ];
        $formParams = $this->addSign($formParams);

        try {
            $response = $this->client->post($checkTokenUri, [
                'form_params' => $formParams,
            ]);
            $code = $response->getStatusCode();
            if ($code != 200) {
                return false;
            }
            $stringBody = strval($response->getBody());
            $result = json_decode($stringBody, true);
            if ($result['errcode'] != 0) {
                return false;
            }

            $this->userId = $result['uid'];
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function bindOpenId($token, $appId, $openId)
    {
        $bindOpenIdUri = "/v1/server/bindOpenId";
        if ($this->config->has('passport.uri.server.bind_openid')) {
            $bindOpenIdUri = $this->config->get('passport.uri.server.bind_openid');
        }

        $formParams = [
            'hb_token' => $token,
            'openid' => $openId,
            'app_id' => $appId,
            'product' => $this->product,
            'nonce' => time(),
        ];
        $formParams = $this->addSign($formParams);

        try {
            $response = $this->client->post($bindOpenIdUri, [
                'form_params' => $formParams,
            ]);
            $code = $response->getStatusCode();
            if ($code != 200) {
                return false;
            }
            $stringBody = strval($response->getBody());
            $result = json_decode($stringBody, true);
            if ($result['errcode'] != 0) {
                return false;
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function getUserInfo($userId)
    {
        if (array_key_exists($userId, $this->userInfo)) {
            return $this->userInfo[$userId];
        }

        $getUserInfoUri = "/v1/server/getUserInfo";
        if ($this->config->has('passport.uri.server.get_user_info')) {
            $getUserInfoUri = $this->config->get('passport.uri.server.get_user_info');
        }

        $formParams = [
            'uid' => $userId,
            'product' => $this->product,
            'nonce' => time(),
        ];
        $formParams = $this->addSign($formParams);

        try {
            $response = $this->client->post($getUserInfoUri, ['form_params' => $formParams]);
            $code = $response->getStatusCode();
            if ($code != 200) {
                return false;
            }
            $stringBody = strval($response->getBody());
            $result = json_decode($stringBody, true);
            if ($result['errcode'] != 0) {
                return false;
            }

            $this->userInfo[$userId] = array(
                'user_id'   => $result['uid'],
                'mobile'    => $result['mobile'],
                'nickname'  => $result['nickname'],
                'avatar'    => $result['avatar'],
                'male'      => $result['male'],
                'birthday'  => $result['birthday'],
                'grade_id'  => $result['grade'],
                'provcode'  => $result['provcode'],
                'citycode'  => $result['citycode'],
            );
            return $this->userInfo[$userId];
        } catch (Exception $e) {
            return false;
        }
    }

    public function getMultiUserInfo($userIdList)
    {
        $resultUserInfo = [];
        $notExistUserIdList = [];
        foreach ($userIdList as $userId) {
            if (array_key_exists($userId, $this->userInfo)) {
                $resultUserInfo[$userId] = $this->userInfo[$userId];
            } else {
                $notExistUserIdList[] = $userId;
            }
        }

        if (!empty($notExistUserIdList)) {
            $getUserInfoUri = "/v1/server/getMultiUserInfo";
            if ($this->config->has('passport.uri.server.getMultiUserInfo')) {
                $getUserInfoUri = $this->config->get('passport.uri.server.getMultiUserInfo');
            }

            $formParams = [
                'uid_list' => implode(',', $notExistUserIdList),
                'product' => $this->product,
                'nonce' => time(),
            ];
            $formParams = $this->addSign($formParams);

            try {
                $response = $this->client->post($getUserInfoUri, ['form_params' => $formParams]);
                $code = $response->getStatusCode();
                if ($code != 200) {
                    return false;
                }
                $stringBody = strval($response->getBody());
                $result = json_decode($stringBody, true);
                if ($result['errcode'] != 0) {
                    return false;
                }

                foreach ($result['user_list'] as $key => $value) {
                    $this->userInfo[$key] = array(
                        'user_id'   => $value['uid'],
                        'mobile'    => $value['mobile'],
                        'nickname'  => $value['nickname'],
                        'avatar'    => $value['avatar'],
                        'male'      => $value['male'],
                        'birthday'  => $value['birthday'],
                        'grade_id'  => $value['grade'],
                        'provcode'  => $value['provcode'],
                        'citycode'  => $value['citycode'],
                    );

                    $resultUserInfo[$key] = $this->userInfo[$key];
                }
            } catch (Exception $e) {
                return false;
            }
        }

        return $resultUserInfo;
    }

    public function getBindUserListByOpenId($openId)
    {
        $getBindUserListUri = "/v1/server/getBindUserListByOpenId";
        if ($this->config->has('passport.uri.server.get_bind_user_list')) {
            $getBindUserListUri = $this->config->get('passport.uri.server.get_bind_user_list');
        }

        $formParams = [
            'app_id' => $this->getWeChatAppId(),
            'openid' => $openId,
            'product' => $this->product,
            'nonce' => time(),
        ];
        $formParams = $this->addSign($formParams);

        try {
            $response = $this->client->post($getBindUserListUri, ['form_params' => $formParams]);
            $code = $response->getStatusCode();
            if ($code != 200) {
                return false;
            }
            $stringBody = strval($response->getBody());
            $result = json_decode($stringBody, true);
            if ($result['errcode'] != 0) {
                return false;
            }

            return $result['bind_list'];
        } catch (Exception $e) {
            return false;
        }
    }

    public function setGrade($userId, $gradeId)
    {
        $setUserInfoUri = "/v1/server/setUserInfo";
        if ($this->config->has('passport.uri.server.set_user_info')) {
            $setUserInfoUri = $this->config->get('passport.uri.server.set_user_info');
        }

        $formParams = [
            'uid' => $userId,
            'product' => $this->product,
            'grade' => $gradeId,
            'nonce' => time(),
        ];
        $formParams = $this->addSign($formParams);

        try {
            $response = $this->client->post($setUserInfoUri, ['form_params' => $formParams]);
            $code = $response->getStatusCode();
            if ($code != 200) {
                return false;
            }
            $stringBody = strval($response->getBody());
            $result = json_decode($stringBody, true);
            if ($result['errcode'] != 0) {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }
    }

    public function generateLoginUrl($redirectUri, $terminal)
    {
        $loginUri = "/v1/login";
        if ($this->config->has('passport.uri.front.login')) {
            $loginUri = $this->config->get('passport.uri.front.login');
        }

        $redirectUri = urlencode($redirectUri);

        return $this->baseUri.$loginUri."?redirect_uri={$redirectUri}&product={$this->product}&terminal={$terminal}";
    }

    public function generateRegisterUrl($redirectUri, $terminal)
    {
        $registerUri = "/v1/register";
        if ($this->config->has('passport.uri.front.register')) {
            $registerUri = $this->config->get('passport.uri.front.register');
        }

        $redirectUri = urlencode($redirectUri);

        return $this->baseUri.$registerUri."?redirect_uri={$redirectUri}&product={$this->product}&terminal={$terminal}";
    }

    public function generateWeChatUrl($redirectUri, $weChatAppId)
    {
        $weChatUri = "/v1/weChat";
        if ($this->config->has('passport.uri.front.weChat')) {
            $weChatUri = $this->config->get('passport.uri.front.weChat');
        }

        $redirectUri = urlencode($redirectUri);

        return $this->baseUri.$weChatUri."?redirect_uri={$redirectUri}&product={$this->product}&terminal=8&wechat_appid={$weChatAppId}";
    }

    private function addSign($params)
    {
        ksort($params);
        $paramStr = http_build_query($params, null, ini_get('arg_separator.output'), PHP_QUERY_RFC3986);
        $sign = md5(md5($paramStr) . $this->secret);

        $params['sign'] = $sign;
        return $params;
    }
}
