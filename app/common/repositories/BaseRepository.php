<?php

// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------


namespace app\common\repositories;

use app\common\dao\BaseDao;
use crmeb\services\ElasticSearch\ElasticSearchService;

/**
 * Class BaseRepository
 * @package app\common\repositories
 */
class BaseRepository
{
    /**
     * @var BaseDao $dao
     */
    protected $dao;

    /**
     * @var ElasticSearchService $es
     */
    protected $es;

    public function setDao($dao){
        $this->dao = $dao;
    }

    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->dao, $name], $arguments);
    }

    public function _doRequestCurl($method, $location, $options = [])
    {

        $curl = curl_init();
        // POST数据设置
        if (strtolower($method) === 'post') {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $options['data'] ?? $options['sign_body'] ?? '');
        }
        // CURL头信息设置
        if (!empty($options['headers'])) {
            $headers = [];
            foreach ($options['headers'] as $k => $v) {
                $headers[] = "$k: $v";
            }
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }
        curl_setopt($curl, CURLOPT_URL, $location);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        $content = curl_exec($curl);
        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        curl_close($curl);
        return json_decode(substr($content, $headerSize), true);
    }
}
