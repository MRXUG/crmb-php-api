<?php

namespace app\common\model\platform;

use app\common\model\BaseModel;

class PlatformCouponProduct extends BaseModel
{

    public static function tablePk(): ?string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'platform_coupon_product';
    }
}
