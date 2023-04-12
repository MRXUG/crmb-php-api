<?php

namespace app\common\model\platform;

use app\common\model\BaseModel;

class PlatformCouponUseScope extends BaseModel
{
    public static function tablePk(): ?string
    {
        return 'use_scope_id';
    }

    public static function tableName(): string
    {
        return 'platform_coupon_use_scope';
    }
}
