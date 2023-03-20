<?php
/**
 * @user: BEYOND 2023/3/5 22:03
 */

namespace app\controller\api\coupon;

use app\validate\api\ReturnCouponValidate;
use crmeb\basic\BaseController;
use crmeb\services\MerchantCouponService;

class ReturnCoupon extends BaseController
{
    /**
     * 退券
     *
     * @param ReturnCouponValidate $returnCouponValidate
     *
     * @return mixed
     */
    public function return(ReturnCouponValidate $returnCouponValidate)
    {
        $params = $this->request->post();
        $returnCouponValidate->check($params);

        $data = MerchantCouponService::create(MerchantCouponService::RETURN_COUPON, $params, $merchantConfig)->coupon()->return($params);

        return app('json')->success($data);
    }
}