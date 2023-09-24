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
class SystemConfigValue extends BaseModel
{

    protected $schema = [
        'config_value_id' => 'int',
        'config_key'      => 'string',
        'value'           => 'string',
        'mer_id'          => 'int',
        'create_time'     => 'datetime',
    ];

    /**
     * @return string
     * @author xaboy
     * @day 2020-03-30
     */
    public static function tablePk(): string
    {
        return 'config_value_id';
    }

    /**
     * @return string
     * @author xaboy
     * @day 2020-03-30
     */
    public static function tableName(): string
    {
        return 'system_config_value';
    }

    /**
     * @param $value
     * @return mixed
     * @author xaboy
     * @day 2020-03-30
     */
    public function getValueAttr($value)
    {
        return explode(",",$value);
    }

    /**
     * @param $value
     * @return false|string
     * @author xaboy
     * @day 2020-03-30
     */
    public function setValueAttr($value)
    {
        return is_array($value) ? implode($value,",") : $value;
    }

    /**
     * 通过配置获取mer_id
     *
     * @param $key
     * @param $value
     *
     * @return mixed
     */
    public function merId($key, $value)
    {
        return SystemConfigValue::getDB()
            ->where('config_key', $key)
            ->where('value', json_encode($value))
            ->value('mer_id');
    }
}
