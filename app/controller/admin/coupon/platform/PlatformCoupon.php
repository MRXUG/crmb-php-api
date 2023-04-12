<?php

namespace app\controller\admin\coupon\platform;

use app\common\repositories\platform\PlatformCouponRepository;
use crmeb\basic\BaseController;
use think\App;
use think\Request;

class PlatformCoupon extends BaseController
{
    private PlatformCouponRepository $repository;

    public function __construct(App $app, PlatformCouponRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 列表
     *
     * @param Request $request
     * @return void
     */
    public function lst(Request $request)
    {

    }

    /**
     * 创建
     *
     * @param Request $request
     * @return void
     */
    public function create(Request $request)
    {
        $param = $request->post();

        dd($param);

        $this->repository->save();
    }

}
