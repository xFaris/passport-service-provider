<?php

return [

    'product'           => 'mp',
    'secret'            => '',

    'bind_host'         => 'x.x.x.x',
    'base_uri'          => 'https://passport.xxx.com',
    'request_timeout'   => 0,
    'connect_timeout'   => 0,

    'uri' => [
        'front' => [
            'login'         => '/v1/login',
            'register'      => '/v1/register',
            'wechat'        => '/v1/weChat',
        ],
        'server' => [
            'check_token'   => '/v1/server/checkToken',
            'get_user_info' => '/v1/server/getUserInfo',
            'set_user_info' => '/v1/server/setUserInfo',
            'bind_openid'   => '/v1/server/bindOpenId',
            'get_bind_user_list' => '/v1/server/getBindUserListByOpenId',
        ],
    ],

    'wechat_appid'      => '',
];
