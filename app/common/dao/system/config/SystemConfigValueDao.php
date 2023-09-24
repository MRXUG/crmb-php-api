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


namespace app\common\dao\system\config;


use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\system\config\SystemConfigValue;
use think\db\exception\DbException;

/**
 * Class SystemConfigValueDao
 * @package app\common\dao\system\config
 * @author xaboy
 * @day 2020-03-27
 */
class SystemConfigValueDao extends BaseDao
{

    /**
     * @return BaseModel
     * @author xaboy
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return SystemConfigValue::class;
    }

    /**
     * @param int $merId
     * @param string $key
     * @param array $data
     * @return int
     * @throws DbException
     * @author xaboy
     * @day 2020-03-27
     */
    public function merUpdate(int $merId, string $key, array $data)
    {
        if (isset($data['value']) && is_array($data['value'])) $data['value'] = implode($data['value'],",");
        return SystemConfigValue::getDB()->where('mer_id', $merId)->where('config_key', $key)->update($data);
    }

    /**
     * @param array $keys
     * @param int $merId
     * @return array
     * @author xaboy
     * @day 2020-04-22
     */
    public function fields(array $keys, int $merId)
    {
        $result = SystemConfigValue::getDB()->whereIn('config_key', $keys)->where('mer_id', $merId)->column('value', 'config_key');
        foreach ($result as $k => $val) {
            $result[$k] = $val;
        }
        return $result;
    }

    /**
     * @param array $keys
     * @param int $merId
     * @return int
     * @throws DbException
     * @author xaboy
     * @day 2020-05-18
     */
    public function clear(array $keys, int $merId)
    {
        return SystemConfigValue::getDB()->whereIn('config_key', $keys)->where('mer_id', $merId)->delete();
    }


    /**
     * @param string $key
     * @param int $merId
     * @return mixed|null
     * @author xaboy
     * @day 2020-05-08
     */
    public function value(string $key, int $merId)
    {
        $value = SystemConfigValue::getDB()->where('config_key', $key)->where('mer_id', $merId)->value('value');
        return $value;
    }

    /**
     * @param string $key
     * @param int $merId
     * @return bool
     * @author xaboy
     * @day 2020-03-27
     */
    public function merExists(string $key, int $merId): bool
    {
        return SystemConfigValue::getDB()->where('config_key', $key)->where('mer_id', $merId)->count() > 0;
    }

    public function getMerSetting($merId): array
    {
        return SystemConfigValue::getDB()
            ->where('mer_id', $merId)
            ->select()->toArray();
    }


    public function getProfitSharingSetting(): array
    {
        return SystemConfigValue::getDB()->whereIn('config_key',[
            'profit_sharing_natural_flow',
            'profit_sharing_advertising_flow',
            'profit_sharing_return_flow_rate',
            'profit_sharing_advertising_switch',
            'profit_sharing_advertising_set',
            'profit_sharing_locking_duration',
            'profit_sharing_natural_flow_profit',
            'profit_sharing_advertising_flow_deposit'
        ])->select()->toArray();
    }


    public function setProfitSharingSetting($configKey, $value, $merId = 0)
    {
       return  SystemConfigValue::getDB()
        ->when($merId > 0, function ($query) use ($merId) {
                $query->where('mer_id', $merId);
            })
            ->where('config_key', $configKey)
        ->find();
    }




}
