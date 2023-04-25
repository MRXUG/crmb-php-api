<?php

namespace app\controller\api\coupon;

use app\common\repositories\platform\PlatformCouponReceiveRepository;
use crmeb\basic\BaseController;
use think\App;
use think\Request;

class PlatformCoupon extends BaseController
{
    private PlatformCouponReceiveRepository $platformCouponReceiveRepository;

    public function __construct(App $app, PlatformCouponReceiveRepository $platformCouponReceiveRepository)
    {
        parent::__construct($app);
        $this->platformCouponReceiveRepository = $platformCouponReceiveRepository;
    }

    public function lst (Request $request)
    {
        $userId = $request->uid();
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 10);
        return $this->json()->success($this->platformCouponReceiveRepository->getList($userId, $page, $limit));
    }
}
