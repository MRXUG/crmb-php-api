<?php

namespace app\common\dao\platform;

use app\common\dao\BaseDao;
use app\common\model\platform\PlatformCouponPosition;

class PlatformCouponPositionDao extends BaseDao
{

    protected function getModel(): string
    {
        return PlatformCouponPosition::class;
    }
}
