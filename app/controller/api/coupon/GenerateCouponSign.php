<?php
/**
 * @user: BEYOND 2023/3/2 21:10
 */

namespace app\controller\api\coupon;

use app\validate\api\SendCouponValidate;
use crmeb\basic\BaseController;
use crmeb\services\MerchantCouponService;

/**
 * 生成领券签名
 */
class GenerateCouponSign extends BaseController
{
    /**
     * 领券签名
     *
     * @return mixed
     */
    public function generateCouponSign(SendCouponValidate $sendCouponValidate)
    {
        /**
         * {
        "status": 200,
        "message": "success",
        "data": {
        "app_id": "wxcc2ff8afd2e40525",
        "mch_id": "1638941761",
        "create_time": "2023-03-05T19:34:41+08:00",
        "stock_id": "1272180000000007"
        }
        }
         */
        $params = $this->request->post();
        $sendCouponValidate->check($params);
        $sendCouponValidate->validateReceiveCoupon($params['stock_list'], $this->request->uid());

        $data = MerchantCouponService::create(MerchantCouponService::SEND_COUPON, [], $merchantConfig)->coupon()->generateSign($params['stock_list'], $merchantConfig);

        return app('json')->success($data);
    }
}