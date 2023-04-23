<?php

namespace crmeb\listens;

use crmeb\interfaces\ListenerInterface;
use crmeb\services\TimerService;
use crmeb\utils\platformCoupon\RefreshPlatformCouponProduct;

class RefreshPlatformCouponListen extends TimerService implements ListenerInterface
{

    protected string $name = '刷新平台优惠券商品: ' . __CLASS__;

    public function handle($event): void
    {
        $this->tick(1000 * 60 * 1, function () {
            RefreshPlatformCouponProduct::runQueue();
        });
    }
}
