<?php

namespace crmeb\services\erp\JuShuiTan\Auth;

use crmeb\services\erp\JuShuiTan\Api\Common\BaseApi;
use crmeb\services\erp\JuShuiTan\Api\Common\Client;
use crmeb\services\erp\JuShuiTan\Api\Common\Util;

class Auth extends BaseApi
{
    /**
     * 生成授权链接
     * @fun createUrl
     * @param $state
     * @return string
     */
    public function createUrl($state): string
    {
        $data = [
            'app_key' => $this->getConfig()['app_key'],
            'timestamp' => time(),
            'charset' => $this->getConfig()['charset'],
            'state' => $state,
        ];
        $sign = Util::getSign($this->getConfig()['app_secret'],$data);
        return $this->getConfig()['authUrl'] .
            '?app_key=' . $data['app_key'] .
            '&timestamp=' . $data['timestamp'] .
            '&charset=' . $data['charset'] .
            '&state=' . $data['state'] .
            '&sign=' . $sign;
    }


    /**
     * 获取访问令牌
     * @fun getAccessToken
     * @param $code
     * @return array
     */
    public function getAccessToken($code): array
    {
        $data = [
            'app_key' => $this->getConfig()['app_key'],
            'timestamp' => time(),
            'grant_type' => 'authorization_code',
            'charset' => $this->getConfig()['charset'],
            'code' => $code,
        ];
        $data['sign'] = Util::getSign($this->getConfig()['app_secret'],$data);
        return Client::post($this->getAccessTokenUrl, $data);
    }

    /**
     * 更新授权令牌
     * @fun refreshToken
     * @param $refresh_token
     * @return array
     */
    public function refreshToken($refresh_token): array
    {
        $data = [
            'app_key' => $this->getConfig()['app_key'],
            'timestamp' => time(),
            'grant_type' => 'refresh_token',
            'charset' => $this->getConfig()['charset'],
            'refresh_token' => $refresh_token,
            'scope' => 'all',
        ];

        $data['sign'] = Util::getSign($this->getConfig()['app_secret'],$data);
        return Client::post($this->refreshTokenUrl, $data);
    }
}