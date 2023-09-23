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


namespace app\common\repositories\system\config;


use app\common\dao\system\config\SystemConfigValueDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\system\groupData\GroupDataRepository;
use app\common\repositories\system\groupData\GroupRepository;
use app\validate\admin\AppletValidate;
use app\validate\admin\ProfitSharingSettingValidate;
use crmeb\jobs\SyncProductTopJob;
use crmeb\services\DownloadImageService;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Queue;

/**
 * Class ConfigValueRepository
 * @package app\common\repositories\system\config
 * @mixin SystemConfigValueDao
 */
class ConfigValueRepository extends BaseRepository
{

    /**
     * ConfigValueRepository constructor.
     * @param SystemConfigValueDao $dao
     */
    public function __construct(SystemConfigValueDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * @param array $keys
     * @param int $merId
     * @return array
     * @author xaboy
     * @day 2020-03-27
     */
    public function more(array $keys, int $merId): array
    {
        $config = $this->dao->fields($keys, $merId);
        foreach ($keys as $key) {
            if (!isset($config[$key])) $config[$key] = '';
        }
        return $config;
    }

    /**
     * @param string $key
     * @param int $merId
     * @return mixed|string|null
     * @author xaboy
     * @day 2020-05-08
     */
    public function get(string $key, int $merId)
    {
        $value = $this->dao->value($key, $merId);
        return $value ?? '';
    }

    /**
     * @param int|array $cid
     * @param array $formData
     * @param int $merId
     * @author xaboy
     * @day 2020-03-27
     */
    public function save($cid, array $formData, int $merId)
    {
        $keys = array_keys($formData);
        $keys = app()->make(ConfigRepository::class)->intersectionKey($cid, $keys);
        if (!count($keys)) return;
        foreach ($keys as $key => $info) {
            if (!isset($formData[$key]))
                unset($formData[$key]);
            else {
                if ($info['config_type'] == 'number') {
                    if ($formData[$key] === '' || $formData[$key] < 0)
                        throw new ValidateException($info['config_name'] . '不能小于0');
                    $formData[$key] = floatval($formData[$key]);
                }
                $this->separate($key,$formData[$key],$merId);
            }
        }
        $this->setFormData($formData, $merId);
    }

    /**
     * TODO 需要做特殊处理的配置参数
     * @param $key
     * @author Qinii
     * @day 2022/11/17
     */
    public function separate($key,$value,$merId)
    {
        switch($key) {
            case 'mer_svip_status':
                //修改商户的会员状态
                app()->make(ProductRepository::class)->getSearch([])->where(['mer_id' => $merId,'product_type' => 0])->update([$key => $value]);
                break;
//            case 'site_ico':
//                //修改ico图标
//                $stie_ico = systemConfig('site_ico');
//                $ico = substr($value,-3);
//                if ($stie_ico != $value && $ico != 'ico') {
//                    $path = app()->make(DownloadImageService::class)->downloadImage($value,'def','favicon.ico',1)['path'];
//                    $value = public_path().$path;
//                    if (!is_file($value)) throw new ValidateException('Ico图标文件不存在');
//                    rename($value, public_path() . 'favicon.ico');
//                }
//                break;
                //热卖排行
            case 'hot_ranking_switch':
                if ($value) {
                    Queue::push(SyncProductTopJob::class, []);
                }
                break;
            case 'svip_switch_status':
                if ($value == 1) {
                    $groupDataRepository = app()->make(GroupDataRepository::class);
                    $groupRepository = app()->make(GroupRepository::class);
                    $group_id = $groupRepository->getSearch(['group_key' => 'svip_pay'])->value('group_id');
                    $where['group_id'] = $group_id;
                    $where['status'] = 1;
                    $count = $groupDataRepository->getSearch($where)->field('group_data_id,value,sort,status')->count();
                    if (!$count)
                        throw new ValidateException('请先添加会员类型');
                }
                break;
            default:
                break;
        }
        return ;
    }

    public function setFormData(array $formData, int $merId)
    {
        Db::transaction(function () use ($merId, $formData) {
            foreach ($formData as $key => $value) {
                if ($this->dao->merExists($key, $merId))
                    $this->dao->merUpdate($merId, $key, ['value' => $value]);
                else
                    $this->dao->create([
                        'mer_id' => $merId,
                        'value' => $value,
                        'config_key' => $key
                    ]);
            }
        });
        // 清除缓存
        foreach ($formData as $key => $value){
            Cache::delete($this->getCacheKey($merId, $key));
        }
    }

    public function getCacheKey(int $merId, string $configKey){
        return sprintf("system_config:%d_%s", $merId, $configKey);
    }

    public function getMerSetting($merId): array
    {
        return array_column($this->dao->getMerSetting($merId), 'value', 'config_key');
    }

    public function setMerSetting($data, $merId)
    {
        foreach ($data as $configKey => $value) {
            $model = $this->dao->setProfitSharingSetting($configKey, json_encode($value), $merId);
            if ($model) {
                $this->dao->update($model->config_value_id, [
                    'value' => json_encode($value)
                ]);
            } else {
                $this->dao->create([
                    'config_key' => $configKey,
                    'value' => $value,
                    'mer_id' => $merId,
                ]);
            }
        }
    }

    public function getProfitSharingSetting(): array
    {
        $data = array_column($this->dao->getProfitSharingSetting(), 'value', 'config_key');
        foreach ($data as &$value) {
            $value = !$value ? 0 : $value;
        }
        return $data;
    }

    public function setProfitSharingSetting(ProfitSharingSettingValidate $validate, $data)
    {
        $validate->check($data);
        foreach ($data as $configKey => $value) {
            $model = $this->dao->setProfitSharingSetting($configKey, $value);
            if ($model) {
                $this->dao->update($model->config_value_id, [
                    'value' => json_encode($value)
                ]);
            } else {
                $this->dao->create([
                    'config_key' => $configKey,
                    'value' => $value,
                ]);
            }
        }
    }

    /**
     * 更新商户信息
     *
     * @param  array  $data
     *
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author  zhoukang <zhoukang@vchangyi.com>
     * @date    2023/3/7 21:01
     */
    public function updateMerchant(array $data)
    {
        foreach ($data as $k => $v) {
            $ref = [
                'config_key' => $k,
                'value'      => $v,
            ];
            $where = [
                'config_key' => $k,
            ];
            $this->dao->createOrUpdate($where, $ref);
        }
    }

}
