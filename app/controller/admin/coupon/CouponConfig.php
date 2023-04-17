<?php

namespace app\controller\admin\coupon;

use app\common\repositories\coupon\CouponConfigRepository;
use crmeb\basic\BaseController;
use crmeb\exceptions\WechatException;
use think\App;
use think\Exception;

class CouponConfig extends BaseController
{

    private $repository;
    public function __construct(App $app, CouponConfigRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }


    public function settingRisks(){
        $params = $this->request->post();

        try {
            $this->repository->updateCouponConfig($params);

        }catch (\Exception $e){
            throw new WechatException('设置失败');
        }

        return app('json')->success('设置成功');
    }
}