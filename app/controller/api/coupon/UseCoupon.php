<?php
/**
 * @user: BEYOND 2023/3/5 21:18
 */

namespace app\controller\api\coupon;

use app\validate\api\UseCouponValidate;
use crmeb\basic\BaseController;
use crmeb\services\MerchantCouponService;

class UseCoupon extends BaseController
{
    /**
     * @return mixed
     * 
     */
    public function use(UseCouponValidate $useCouponValidate)
    {
        $params = $this->request->post();
        $useCouponValidate->check($params);

        $data = MerchantCouponService::create(MerchantCouponService::USE_COUPON, $params, $merchantConfig)->coupon()->use($params);
        return app('json')->success($data);
    }
}