<?php

namespace app\common\dao\platform;

use app\common\dao\BaseDao;
use app\common\model\platform\PlatformCoupon;

class PlatformCouponDao extends BaseDao
{

    protected function getModel(): string
    {
        return PlatformCoupon::class;
    }
}
