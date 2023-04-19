<?php

/**
 * @user: BEYOND 2023/3/2 21:10
 */

namespace app\controller\api\coupon;

use app\common\dao\coupon\CouponStocksUserDao;
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
         * 生成券签名
         */
        $params = $this->request->post();
        $sendCouponValidate->check($params);
        $sendCouponValidate->validateReceiveCoupon($params['stock_list'], $this->request->uid());

        $data = MerchantCouponService::create(MerchantCouponService::SEND_COUPON, [], $merchantConfig)->coupon()->generateSign($params['stock_list'], $merchantConfig);

        return app('json')->success($data);
    }


    /**
     * 领券签名
     *
     * @return mixed
     */
    public function generatePlatformCouponSign(SendCouponValidate $sendCouponValidate)
    {
        /**
         * 生成券签名
         */
        $params = $this->request->post();
        $sendCouponValidate->check($params);
        $sendCouponValidate->validateReceivePlatformCoupon($params['stock_list'], $this->request->uid());

        $data = MerchantCouponService::create(MerchantCouponService::SEND_COUPON, [], $merchantConfig)->coupon()->generateSign($params['stock_list'], $merchantConfig);

        return app('json')->success($data);
    }

    public function generateCouponSign2(SendCouponValidate $sendCouponValidate, CouponStocksUserDao $CouponStocksUserDao)
    {
        $params = $this->request->post();
        $uid = $this->request->uid() ? $this->request->uid() : 0;

        if ($uid == 0) {
            /**
             * 生成券签名
             */
            // $generateCouponSign = app()->make(GenerateCouponSign::class);
            // $generateCouponSign->generateCouponSign($sendCouponValidate);
            $sendCouponValidate->check($params);
            $sendCouponValidate->validateReceiveCoupon($params['stock_list'], $this->request->uid());

            $data = MerchantCouponService::create(MerchantCouponService::SEND_COUPON, [], $merchantConfig)->coupon()->generateSign($params['stock_list'], $merchantConfig);

            return app('json')->success($data);
        } else {
            foreach ($params['stock_list'] as $item) {
                $stockId = $item['stock_id'];
                $has_coupons = $CouponStocksUserDao->userReceivedCoupon($stockId, $uid)->select()->toArray();
                $sendCouponValidate->check($params);
                $sendCouponValidate->validateReceiveCoupon($params['stock_list'], $this->request->uid());

                $data = MerchantCouponService::create(MerchantCouponService::SEND_COUPON, [], $merchantConfig)->coupon()->generateSign($params['stock_list'], $merchantConfig);
                $data['coupon_count'] = $has_coupons;
                return app('json')->success($data);
            }
        }
    }
}
