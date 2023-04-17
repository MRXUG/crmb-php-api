<?php

namespace app\common\model\coupon;

use app\common\model\BaseModel;
use app\common\model\store\coupon\StoreCouponIssueUser;
use app\common\model\user\User;
use think\model\relation\HasOne;

class CouponConfig extends BaseModel
{

    public static function tablePk(): ?string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'coupon_config';
    }
}
