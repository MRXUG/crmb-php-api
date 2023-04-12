<?php

namespace app\common\model\platform;

use app\common\model\BaseModel;

class PlatformCouponPosition extends BaseModel
{

    public static function tablePk(): ?string
    {
        return "platform_coupon_position_id";
    }

    public static function tableName(): string
    {
        return "platform_coupon_position";
    }
}
