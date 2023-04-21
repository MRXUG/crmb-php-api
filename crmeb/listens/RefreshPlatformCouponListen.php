<?php

namespace crmeb\listens;

use crmeb\interfaces\ListenerInterface;
use crmeb\services\TimerService;
use crmeb\utils\platformCoupon\RefreshPlatformCouponProduct;
use think\facade\Queue;

class RefreshPlatformCouponListen extends TimerService implements ListenerInterface
{

    public function handle($event): void
    {
        $this->tick(1000 * 60 * 30, function () {
            RefreshPlatformCouponProduct::runQueue();
        });
    }
}
