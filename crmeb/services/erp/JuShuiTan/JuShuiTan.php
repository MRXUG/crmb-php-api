<?php

namespace crmeb\services\erp\JuShuiTan;

use app\common\model\erp\JuShuiTanAuthorizeConfig;
use GuzzleHttp\Client;
use think\Exception;

class JuShuiTan
{
    /**
     * 全局config配置
     * @var array
     */
    protected array $config = [
        'authUrl' => 'https://openweb.jushuitan.com/auth',
        'base_url' => 'https://openweb.jushuitan.com',
        'access_token' => '',
        'app_key' => '',
        'app_secret' => '',
        'version' => 2,
        'charset' => 'utf-8'
    ];

    /**
     * Snake
     * 公共请求参数
     * @var array|string[]
     */
    protected array $publicRequestParams = [
        'app_key' => '',
        'access_token' => '',
        'timestamp' => '',
        'charset' => '',
        'version' => '',
    ];

    /**
     * Client请求
     * @var Client
     */
    protected Client $client;

    /**
     * 定义获取access—token Url
     * @var string
     */
    protected string $getAccessTokenUrl = 'https://openapi.jushuitan.com/openWeb/auth/getInitToken';

    /**
     * 定义refresh-token地址
     * @var string
     */
    protected string $refreshTokenUrl = 'https://openapi.jushuitan.com/openWeb/auth/refreshToken';

    /**
     * 获取config配置
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * 设置config配置
     * @param array $config
     * @return JuShuiTan
     */
    public function setConfig(array $config): JuShuiTan
    {
        if (isset($config['app_key'], $config['app_secret'],$config['access_token'])) {
            $this->config['access_token'] = $config['access_token'];
            $this->config['app_key'] = $config['app_key'];
            $this->config['app_secret'] = $config['app_secret'];
        }
        if(isDebug()){
            $this->config['base_url'] = 'https://dev-api.jushuitan.com';
            $this->config['app_key'] = 'b0b7d1db226d4216a3d58df9ffa2dde5';
            $this->config['app_secret'] = '99c4cef262f34ca882975a7064de0b87';
            //企业版token:b7e3b1e24e174593af8ca5c397e53dad, 专业版:8db141b6d724211b28d2eff2c93fe918
            $this->config['access_token'] = 'b7e3b1e24e174593af8ca5c397e53dad';
        }
        return $this;
    }

    /**
     * 获取公共参数
     * @return array
     */
    public function getPublicRequestParams(): array
    {
        return $this->publicRequestParams;
    }

    /**
     * 设置公共参数
     */
    public function setPublicRequestParams(): JuShuiTan
    {
        if (isset($this->getConfig()['app_key'], $this->getConfig()['access_token'])){
            $this->publicRequestParams = [
               'app_key' => $this->config['app_key'],
               'access_token' =>  $this->config['access_token'],
               'timestamp' => time(),
               'charset' => $this->config['charset'],
               'version' => $this->config['version'],
           ];
        }
       return $this;
    }
}
