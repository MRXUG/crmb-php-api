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
     * 选卷
     *
     * @return mixed
     */
    public function selectCoupon(Request $request)
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 10);

        return app('json')->success($this->repository->selectCoupon($page, $limit));
    }

    /**
     * 创建
     *
     * @param Request $request
     * @return void
     */
    public function create(Request $request)
    {
        $this->repository->save($request->post());
        return app('json')->success();
    }

    /**
     * 修改
     *
     * @param int $id
     * @param Request $request
     * @return mixed
     */
    public function update(int $id, Request $request) {
        $this->repository->save($request->post(), $id);
        return app('json')->success();
    }
}
