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

namespace app\common\model\system\config;

use app\common\model\BaseModel;

/**
 * Class SystemConfigValue
 * @package app\common\model\system\config
 * @author xaboy
 * @day 2020-03-30
 */
class MerchantPayConf extends BaseModel
{

    protected $schema = [
        'mer_id' => 'int',
        'mch_id'      => 'string',
        'pemkey'      => 'string',
        'pemcert'      => 'string',
        'serial_no'           => 'string',
        'api_secret'           => 'string',
        'mch_name'=>'string',
        'platform_cert'          => 'string',
        'apiv3_secret'     => 'string',
    ];

    /**
     * @return string
     * @author xaboy
     * @day 2020-03-30
     */
    public static function tablePk(): string
    {
        return 'mer_id';
    }

    /**
     * @return string
     * @author xaboy
     * @day 2020-03-30
     */
    public static function tableName(): string
    {
        return 'merchant_pay_conf';
    }
}
