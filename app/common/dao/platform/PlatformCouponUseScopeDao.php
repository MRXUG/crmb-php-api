<?php

namespace app\common\dao\platform;

use app\common\dao\BaseDao;
use app\common\model\platform\PlatformCouponUseScope;

class PlatformCouponUseScopeDao extends BaseDao
{

    protected function getModel(): string
    {
        return PlatformCouponUseScope::class;
    }
}
