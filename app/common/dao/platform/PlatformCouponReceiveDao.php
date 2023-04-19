<?php

namespace app\common\dao\platform;

use app\common\dao\BaseDao;
use app\common\model\platform\PlatformCouponReceive;
use app\common\model\platform\PlatformCouponUseScope;

class PlatformCouponReceiveDao extends BaseDao
{

    protected function getModel(): string
    {
        return PlatformCouponReceive::class;
    }
}
