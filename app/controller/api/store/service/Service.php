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


namespace app\controller\api\store\service;


use app\common\dao\system\config\SystemConfigDao;
use app\common\repositories\store\service\StoreServiceLogRepository;
use app\common\repositories\store\service\StoreServiceRepository;
use app\common\repositories\store\service\StoreServiceUserRepository;
use app\common\repositories\system\config\ConfigClassifyRepository;
use app\common\repositories\system\config\ConfigRepository;
use app\common\repositories\system\config\ConfigValueRepository;
use app\common\repositories\system\ExtendRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use crmeb\basic\BaseController;
use FormBuilder\Exception\FormBuilderException;
use think\App;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Cache;

/**
 * Class Service
 * @package app\controller\api\store\service
 * @author xaboy
 * @day 2020/5/29
 */
class Service extends BaseController
{
    /**
     * @var StoreServiceRepository
     */
    protected $repository;

    /**
     * @var 
     */
    protected $configRepository;

    /**
     * Service constructor.
     *
     * @param App $app
     * @param StoreServiceRepository $repository
     * @param ConfigRepository $configRepository
     */
    public function __construct(App $app, StoreServiceRepository $repository, ConfigRepository $configRepository)
    {
        parent::__construct($app);
        $this->repository = $repository;
        $this->configRepository = $configRepository;
    }

    /**
     * @param $id
     * @param StoreServiceLogRepository $repository
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020/6/15
     */
    public function chatHistory($id, StoreServiceLogRepository $repository)
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success($repository->userList($id, $this->request->uid(), $page, $limit));
    }

    /**
     * @param StoreServiceLogRepository $repository
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020/6/16
     */
    public function getList(StoreServiceUserRepository $repository)
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success($repository->userMerchantList($this->request->uid(), $page, $limit));
    }

    /**
     * @param StoreServiceLogRepository $repository
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020/6/16
     */
    public function serviceUserList($merId, StoreServiceUserRepository $repository)
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success($repository->merUserList($merId, $this->request->uid(), $page, $limit));
    }

    /**
     * @param $merId
     * @param $id
     * @param StoreServiceLogRepository $repository
     * @return mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @author xaboy
     * @day 2020/6/15
     */
    public function serviceHistory($merId, $id, StoreServiceLogRepository $repository)
    {
        [$page, $limit] = $this->getPage();
        return app('json')->success($repository->merList($merId, $id, $this->request->uid(), $page, $limit));
    }

    public function user($merId, $uid, StoreServiceUserRepository $serviceUserRepository)
    {
        if (!$service = $this->repository->search([
            'uid' => $this->request->uid(),
            'mer_id' => (int)$merId,
            'status' => 1
        ])->find()) {
            return app('json')->fail('没有权限');
        }
        $user = $serviceUserRepository->search(['uid' => $uid, 'service_user_id' => $service->service_user_id])->with(['user' => function ($query) use ($merId) {
            $query->field('uid,avatar,nickname,is_promoter' . (!$merId ? ',phone' : ''));
        }, 'mark' => function ($query) use ($merId) {
            $query->where('mer_id', $merId)->bind(['mark' => 'extend_value']);
        }])->find();
        if (!$user) {
            return app('json')->fail('用户不存在');
        }
        return app('json')->success($user->toArray());
    }

    public function mark($merId, $uid, StoreServiceUserRepository $serviceUserRepository, ExtendRepository $extendRepository)
    {
        $data = $this->request->params(['mark']);
        if (!$service = $this->repository->search([
            'uid' => $this->request->uid(),
            'mer_id' => (int)$merId,
            'status' => 1
        ])->find()) {
            return app('json')->fail('没有权限');
        }
        if ($service->mer_id && !$serviceUserRepository->existsWhere(['uid' => (int)$uid, 'mer_id' => $service->mer_id])) {
            return app('json')->fail('用户不存在');
        }
        $extendRepository->updateInfo(ExtendRepository::TYPE_SERVICE_USER_MARK, (int)$uid, $service->mer_id, (string)$data['mark']);
        return app('json')->success('备注成功');
    }

    public function merchantInfo($id)
    {
        if ($id) {
            $merchant = app()->make(MerchantRepository::class)->get((int)$id);
            if (!$merchant)
                return app('json')->fail('商户不存在');
            $data = [
                'mer_id' => $merchant['mer_id'],
                'avatar' => $merchant['mer_avatar'],
                'name' => $merchant['mer_name'],
            ];
        } else {
            $config = systemConfig(['site_logo', 'site_name']);
            $data = [
                'mer_id' => 0,
                'avatar' => $config['site_logo'],
                'name' => $config['site_name'],
            ];
        }
        return app('json')->success($data);
    }

    public function scanLogin($key)
    {
        $serviceId = (int)$this->request->param('service_id');
        if (!$serviceId || !$service = $this->repository->search([
                'uid' => $this->request->uid(),
                'service_id' => $serviceId,
            ])->find()) {
            return app('json')->fail('用户不存在');
        }
        if (!$service['is_open'] || !$service['status']) {
            return app('json')->fail('账号已被关闭');
        }
        if (Cache::has('_scan_ser_login' . $key))
            Cache::set('_scan_ser_login' . $key, $serviceId);
        else
            return app('json')->fail('操作超时');

        return app('json')->success('登录成功');
    }

    /**
     * 平台客服
     *
     * @param int $merId
     *
     * @return mixed
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/2/27 15:54
     */
    public function customerService($merId = 0)
    {
        // 平台联系地址
        $platformKey = 'platform_qy_customer_chat_url';
        // 商户联系地址
        $merKey = 'mer_contact_address';
        // 商户联系方式
        $merWay = 'services_type';

        $keyList = [$platformKey, $merKey, $merWay];
        // 检测是否开启-企业微信平台客服
        $systemConfigDao = app()->make(ConfigRepository::class);
        $configValueRepository = app()->make(ConfigValueRepository::class);

        $newKeys = [];
        foreach ($keyList as $key) {
            if ($systemConfigDao->keyExists($key)) {
                array_push($newKeys, $key);
            }
        }
        
        // 平台客服地址
        $platformQyCustomerChatUrl = [];
        $key = array_search($platformKey, $newKeys);
        if (!$merId || $key !== false) {
            $platformQyCustomerChatUrl = $configValueRepository->more([$platformKey], 0);
            unset($newKeys[$key]);
        }
        
        // 商家客服地址
        $data = $configValueRepository->more($newKeys, $merId);
        return app('json')->success(array_merge($data, $platformQyCustomerChatUrl));
    }
}
