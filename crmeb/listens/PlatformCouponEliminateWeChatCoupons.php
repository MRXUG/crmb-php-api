<?php

namespace crmeb\listens;

use app\common\model\platform\PlatformCouponReceive;
use crmeb\interfaces\ListenerInterface;
use crmeb\services\MerchantCouponService;
use crmeb\services\TimerService;
use think\facade\Db;
use think\facade\Log;
use Exception;
use Throwable;
use ValueError;

class PlatformCouponEliminateWeChatCoupons extends TimerService implements ListenerInterface
{

    protected string $name = '自动消除平台优惠券 微信已领取优惠券失效: ' . __CLASS__;

    /**
     * 李卓镇楼没有BUG
     *   _     ___   ______   _ _   _  ___    _   _ ___ _   _   ____ ___
     * | |   |_ _| |__  / | | | | | |/ _ \  | \ | |_ _| | | | | __ )_ _|
     * | |    | |    / /| |_| | | | | | | | |  \| || || | | | |  _ \| |
     * | |___ | |   / /_|  _  | |_| | |_| | | |\  || || |_| | | |_) | |
     * |_____|___| /____|_| |_|\___/ \___/  |_| \_|___|\___/  |____/___|
     *
     * @param $event
     * @return void
     */
    public function handle($event): void
    {
        $this->tick(1000 * 60 * 30, function () {
            # 获取还剩一天就到期 进行失效处理
            $nowDate = date("Y-m-d H:i:s", strtotime("-1 day"));

            $model = fn () => PlatformCouponReceive::getInstance()->alias('a')
                ->leftJoin('eb_platform_coupon b', 'a.platform_coupon_id = b.platform_coupon_id')
                ->where([
                    ['a.end_use_time', '<', $nowDate],
                    ['b.status', '=', 1],
                    ['a.wx_coupon_destroy', '=', 0]
                ]);


            $count = $model()->count('a.id');
            # 计算十分之一
            $num = (int) ceil(bcmul($count, 0.1, 2));
            /** @var PlatformCouponReceive[] $list */
            $list = $model()->field(['a.*', 'b.wechat_business_number'])->limit($num)->order('a.id', 'desc')->select();
            # 循环失效
            foreach ($list as $item) {
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

            $runCount = count($list);

            Log::info("自动消除平台优惠券 微信已领取优惠券失效: 本次完成失效 {$runCount} 个");
        });
    }
}
