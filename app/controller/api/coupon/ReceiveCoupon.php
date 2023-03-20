<?php
/**
 * @user: BEYOND 2023/3/10 19:40
 */

namespace app\controller\api\coupon;

use app\common\repositories\coupon\BuildCouponRepository;
use app\common\repositories\coupon\CouponStocksUserRepository;
use app\common\repositories\wechat\WechatUserRepository;
use crmeb\basic\BaseController;
use think\exception\ValidateException;

class ReceiveCoupon extends BaseController
{
    /**
     * 领券时调用
     *
     * @param CouponStocksUserRepository $couponStocksUserRepository
     *
     * @return mixed
     */
    public function receive_bak(CouponStocksUserRepository $couponStocksUserRepository)
    {
        $params = $this->request->post();
        foreach ($params['coupon'] as $item) {
            if ($adId = $item['ad_id'] ?? '') {
                $couponCode = $item['coupon_code'] ?? '';
                if (empty($couponCode)) {
                    throw new ValidateException('券编码不能为空');
                }
                $where = [
                    'coupon_code' => $item['coupon_code'],
                ];
                $data = [
                    'ad_id' => $adId,
                ];

                $couponStocksUserRepository->updateWhere($where, $data);
            }
        }

        return app('json')->success([]);
    }

    public function receive(CouponStocksUserRepository $couponStocksUserRepository)
    {
        $params = $this->request->post();
        $uid = $this->request->uid();
        $user = $this->request->userInfo();
        $wechatUserId = $user->wechat_user_id;

        /**
         * @var WechatUserRepository $wechatUserRepository
         */
        $wechatUserRepository = app()->make(WechatUserRepository::class);
        $wechatUser = $wechatUserRepository->getOne(['wechat_user_id' => $wechatUserId], 'wechat_user_id, unionid, routine_openid');

        /**
         * @var BuildCouponRepository $buildCouponRepository
         */
        $buildCouponRepository = app()->make(BuildCouponRepository::class);

        foreach ($params['coupon'] as $item) {
            $couponCode = $item['coupon_code'] ?? '';
            $stockId = $item['stock_id'] ?? '';
            $stockInfo = $buildCouponRepository->stockProduct(['stock_id' => $stockId]);
            if (empty($couponCode) || empty($stockInfo)) {
                throw new ValidateException('券编码不能为空');
            }

            // 计算券的开始和结束时间
            // 券开始核销时间
            $availableTime = $stockInfo['start_at'];
            // 券停止核销时间
            $unAvailableTime = $stockInfo['end_at'];
            // 领取后N天内有效
            $availableDayAfterReceive = (int)$stockInfo['available_day_after_receive'] ?: 0;
            // 领取第N天后生效
            $waitDaysAfterReceive = (int)$stockInfo['wait_days_after_receive'] ?: 0;
            // 开始
            $startTime = date('Y-m-d H:i:s', strtotime("+$waitDaysAfterReceive day"));
            // 结束
            $delay = $waitDaysAfterReceive + $availableDayAfterReceive;
            $endTime = date('Y-m-d H:i:s', strtotime("+$delay day"));
            $start = $waitDaysAfterReceive == 0 ? $availableTime : ($startTime > $availableTime ? $startTime : $availableTime);
            $end = $availableDayAfterReceive == 0 ? $unAvailableTime : ($endTime < $unAvailableTime ? $endTime : $unAvailableTime);

            $where = [
                'coupon_code' => $item['coupon_code'],
            ];
            if (!$item["out_request_no"]){
                throw new ValidateException('商户号不存在');
            }
            $out_request_no = explode("_",$item["out_request_no"]);

            if (count($out_request_no) < 2){
                throw new ValidateException('商户号不存在');
            }
            $data = [
                'mer_id'      => $stockInfo['mer_id'] ?? 0,
                'ad_id'       => $item['ad_id'] ?? 0,
                'uid'         => $uid,
                'coupon_code' => $item['coupon_code'],
                'unionid'     => $wechatUser['unionid'],
                'stock_id'    => $stockId,
                'start_at'    => $start,
                'end_at'      => $end,
                'appid'      => $out_request_no[0],
                'mch_id'      => $out_request_no[1],
                'create_time' => date('Y-m-d H:i:s'),
            ];

            $couponStocksUserRepository->createUpdate($where, $data);
        }

        return app('json')->success([]);
    }

}