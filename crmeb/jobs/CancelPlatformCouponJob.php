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

class CancelPlatformCouponJob implements JobInterface
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
                $platformCouponReceive = PlatformCouponReceive::getInstance()->alias('a')
                    ->field(['a.*', 'b.wechat_business_number'])
                    ->leftJoin('eb_platform_coupon b', 'a.platform_coupon_id = b.platform_coupon_id')
                    ->where([
                        ['a.platform_coupon_id', '=', $data['platform_coupon_id']],
                        ['a.status', '=', 1]
                    ])->select();

                foreach ($platformCouponReceive as $item) {
                    $config = [];

                    Db::startTrans();
                    try {
                        $wx = MerchantCouponService::createFromBusinessNumber($item['wechat_business_number'], $config);

                        $wx->coupon()->expiredCoupon($item->getAttr('coupon_code'), $item->getAttr('stock_id'));

                        PlatformCouponReceive::destroyWxCouponStatus($item->getAttr('id'));
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
