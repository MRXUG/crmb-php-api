<?php

namespace app\common\model\coupon;

use app\common\model\BaseModel;
use app\common\model\store\coupon\StoreCouponIssueUser;
use app\common\model\user\User;
use think\model\relation\HasOne;

class CouponStocksUser extends BaseModel
{

    public static function tablePk(): ?string
    {
        return 'coupon_user_id';
    }

    public static function tableName(): string
    {
        return 'coupon_stocks_user';
    }

    public function stockDetail(): HasOne
    {
        return $this->hasOne(CouponStocks::class, 'stock_id', 'stock_id');
    }

    public function userDetail(): HasOne
    {
        return $this->hasOne(User::class, 'uid', 'uid');
    }

}