<?php

namespace crmeb\jobs;

use app\common\model\platform\PlatformCouponReceive;
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
                /** @var PlatformCouponReceive[] $platformCouponReceive */
                $platformCouponReceive = PlatformCouponReceive::getInstance()
                    ->field(['user_id,stock_id,coupon_code,mch_id'])
                    ->where([
                        ['user_id', '=', $data['uid']],
                        ['status', '=', 0]
                    ])->select();

                foreach ($platformCouponReceive as $item) {
                    $config = [];

                    $wx = MerchantCouponService::createFromBusinessNumber($item['mch_id'], $config);

                    @$wx->coupon()->expiredCoupon($item->getAttr('coupon_code'), $item->getAttr('stock_id'));
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