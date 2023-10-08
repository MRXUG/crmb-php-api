<?php

namespace crmeb\services\erp\JuShuiTan\Api\Common;

use crmeb\services\erp\JuShuiTan\JuShuiTan;

class BaseApi extends JuShuiTan
{
    public function __construct($config)
    {
        parent::setConfig($config);
        parent::setPublicRequestParams();
        Util::setParams($this);
        Client::setUrl($this->config['baseUrl']);
    }
}