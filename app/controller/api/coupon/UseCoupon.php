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
     *      * {
    "data": {
    "send_coupon_result": [
    {
    "code": "SUCCESS",
    "coupon_code": "1217124432016880936800",
    "coupon_name": "千流-0305",
    "discount": "0.01元",
    "end_time": "1678118399",
    "logo": "https://wx.gtimg.com/resource/feuploader/202106/holder_logo_240x240.png",
    "message": "发券成功",
    "out_request_no": "16389417612023030520590378608_0",
    "stock_id": "1272180000000008",
    "use_condition": "满0.01元可用"
    }
    ]
    },
    "errcode": 0,
    "graphid": 33617770
    }
     */
    public function use(UseCouponValidate $useCouponValidate)
    {
        $params = $this->request->post();
        $useCouponValidate->check($params);

        $data = MerchantCouponService::create(MerchantCouponService::USE_COUPON, $params, $merchantConfig)->coupon()->use($params);
        return app('json')->success($data);
    }
}