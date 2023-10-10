<?php

namespace crmeb\services\erp\JuShuiTan\Api;

use crmeb\services\erp\JuShuiTan\Api\Common\BaseApi;
use crmeb\services\erp\JuShuiTan\Api\Common\Client;
use crmeb\services\erp\JuShuiTan\Api\Common\ServeHttp;
use crmeb\services\erp\JuShuiTan\Api\Common\Util;

class ApiRequest extends BaseApi implements ServeHttp
{
    public function request($serveHttp, $params): array
    {
        return Client::post($serveHttp, Util::getParams($this->getConfig()['app_secret'], $params));
    }

}