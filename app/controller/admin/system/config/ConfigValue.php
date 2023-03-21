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


namespace app\controller\admin\system\config;


use app\common\dao\system\config\SystemConfigValueDao;
use app\common\model\system\config\SystemConfigValue;
use app\common\repositories\system\config\ConfigClassifyRepository;
use app\common\repositories\system\config\ConfigValueRepository;
use app\validate\admin\ProfitSharingSettingValidate;
use crmeb\basic\BaseController;
use think\App;
use think\facade\Log;

/**
 * Class ConfigValue
 * @package app\controller\admin\system\config
 * @author xaboy
 * @day 2020-03-27
 */
class ConfigValue extends BaseController
{
    /**
     * @var ConfigClassifyRepository
     */
    private                      $repository;
    private SystemConfigValueDao $valueDao;

    /**
     * ConfigValue constructor.
     * @param App $app
     * @param ConfigValueRepository $repository
     */
    public function __construct(App $app, ConfigValueRepository $repository, SystemConfigValueDao $valueDao)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->valueDao = $valueDao;
    }

    /**
     * @param string $key
     * @return mixed
     * @author xaboy
     * @day 2020-04-22
     */
    public function save($key)
    {
        $formData = $this->request->post();
        if (!count($formData)) return app('json')->fail('保存失败');

        // 服务方式 0-线上客服 1-拨打电话
        if (isset($formData['services_type'])) {
            if (isset($formData['mer_contact_address']) && empty($formData['mer_contact_address'])) {
                return app('json')->fail('联系客服地址不能为空');
            } else if ($formData['services_type'] == 0 && (!preg_match("/https:\/\//", $formData['mer_contact_address']))) {
                return app('json')->fail('联系客服地址错误');
            }
        }

        if (isset($formData['platform_qy_customer_chat_url']) && empty($formData['platform_qy_customer_chat_url'])) {
            return app('json')->fail('联系客服地址不能为空');
        } elseif (isset($formData['platform_qy_customer_chat_url']) && !preg_match("/https:\/\//", $formData['platform_qy_customer_chat_url'])) {
            return app('json')->fail('联系客服地址错误');
        }
//        dd($formData);
        /** @var ConfigClassifyRepository $make */
        $make = app()->make(ConfigClassifyRepository::class);
        if (!($cid = $make->keyById($key))) return app('json')->fail('保存失败');
        $children = array_column($make->children($cid, 'config_classify_id')->toArray(), 'config_classify_id');
        $children[] = $cid;

        $this->repository->save($children, $formData, $this->request->merId());
        return app('json')->success('保存成功');
    }

    /**
     * 店铺设置
     *
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/14 14:10
     */
    public function info()
    {
        $data = $this->repository->getMerSetting($this->request->merId());
        $data['mer_open_receipt'] = $data['mer_open_receipt'] ?? 1;
        $data['services_type'] = $data['services_type'] ?? 0;
        Log::info('获取店铺设置，mer_id：'.$this->request->merId());
        return app('json')->success($data);
    }

    /**
     * 店铺设置
     *
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/16 12:02
     */
    public function setMerSetting()
    {
        $data = $this->request->params([
            'mer_open_receipt',
            'mer_store_stock',
            'set_phone' ,
            'mer_refund_address',
            'mer_refund_user',
            'services_type',
            'mer_contact_address'
        ]);
        $data['mer_open_receipt'] = $data['mer_open_receipt'] ?? 1;
        $data['services_type'] = $data['services_type'] ?? 0;
        $this->repository->setMerSetting($data, $this->request->merId());
        Log::info('设置店铺设置，mer_id：'.$this->request->merId());

        return app('json')->success('设置成功');
    }

    /**
     * 获取分佣设置
     *
     * @return mixed
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/2 11:56
     */
    public function getProfitSharingSetting()
    {
        return app('json')->success($this->repository->getProfitSharingSetting());
    }

    /**
     * 配置分佣设置
     *
     * @param ProfitSharingSettingValidate $validate
     *
     * @return mixed
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/2 11:56
     */
    public function setProfitSharingSetting(ProfitSharingSettingValidate $validate)
    {
        $data = $this->request->params([
            'profit_sharing_natural_flow',
            'profit_sharing_advertising_flow',
            'profit_sharing_return_flow_rate',
            'profit_sharing_advertising_switch',
            'profit_sharing_advertising_set',
            'profit_sharing_locking_duration',
            'profit_sharing_natural_flow_profit',
            'profit_sharing_advertising_flow_deposit'
        ]);

        $this->repository->setProfitSharingSetting($validate, $data);

        return app('json')->success('设置成功');
    }
}
