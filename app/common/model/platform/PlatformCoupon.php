<?php

namespace app\common\model\platform;

use app\common\model\BaseModel;

class PlatformCoupon extends BaseModel
{

    public static function tablePk(): ?string
    {
        return 'platform_coupon_id';
    }

    public static function tableName(): string
    {
        return 'platform_coupon';
    }
}
