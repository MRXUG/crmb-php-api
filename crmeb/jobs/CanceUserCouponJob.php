<?php

namespace crmeb\jobs;

use app\common\model\platform\PlatformCouponReceive;
use app\common\model\coupon\CouponStocksUser;
use crmeb\interfaces\JobInterface;
use crmeb\services\MerchantCouponService;
use think\facade\Db;
use think\facade\Log;
use think\queue\Job;
use Exception;
use ValueError;
use Throwable;

class CanceUserCouponJob implements JobInterface
{

    /**
     * @param Job $job
     * @param array{platform_coupon_id: int} $data
     * @return void
     */
    public function fire($job, $data): void
    {
        try {
            Db::transaction(function () use ($data) {
                $platformCouponReceive = fn() => PlatformCouponReceive::getInstance();
                $couponReceiveData = $platformCouponReceive()->field(['id,user_id,stock_id,coupon_code,mch_id'])
                    ->where([
                        ['user_id', '=', $data['user_id']],
                        ['status', '=', 0]
                    ])->select();

                foreach ($couponReceiveData as $item) {
                    $config = [];

                    Db::startTrans();
                    try {
                        $wx = MerchantCouponService::createFromBusinessNumber($item['mch_id'], $config);

                        $wx->coupon()->expiredCoupon($item->getAttr('coupon_code'), $item->getAttr('stock_id'));

                        PlatformCouponReceive::destroyWxCouponStatus($item->getAttr('id'));

                        $platformCouponReceive()->where(['id'=>$item->getAttr('id')])->limit(1)->delete();
                        Db::commit();
                    } catch (Exception|Throwable|ValueError $e) {
                        Db::rollback();
                    }

                }

                $couponStocksUser = fn() => CouponStocksUser::getInstance();
                $couponStocksUserDate = $couponStocksUser()->field(['sss,uid,stock_id,coupon_code,mch_id'])
                    ->where([
                        ['uid', '=', $data['user_id']],
                        ['written_off', '=', 0],
                        ['is_del', '=', 0]
                    ])->select();
                foreach ($couponStocksUserDate as $item) {
                    $config = [];

                    Db::startTrans();
                    try {
                        $wx = MerchantCouponService::createFromBusinessNumber($item['mch_id'], $config);

                        $wx->coupon()->expiredCoupon($item->getAttr('coupon_code'), $item->getAttr('stock_id'));

                        PlatformCouponReceive::destroyWxCouponStatus($item->getAttr('sss'));

                        $couponStocksUser()->where(['sss'=>$item->getAttr('sss')])->limit(1)->delete();
                        Db::commit();
                    } catch (Exception|Throwable|ValueError $e) {
                        Db::rollback();
                    }
                }
            });
        } catch (Exception|ValueError|Throwable $e) {
            Log::error("取消平台优惠券 " . $e->getMessage() . $e->getTraceAsString());
        }

        $job->delete();
    }

    public function failed($data)
    {

    }
}
