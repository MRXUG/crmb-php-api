<?php
/**
 *
 * @user: zhoukang
 * @data: 2023/3/6 18:24
 */

namespace app\common\model\system\merchant;

use app\common\model\BaseModel;
use think\model\relation\HasMany;
use app\common\model\coupon\CouponStocks;
use think\model\relation\HasOne;

class MerchantAdCoupon extends BaseModel
{
    /**
     * @return string
     * @day 2020-03-30
     */
    public static function tablePk(): string
    {
        return 'id';
    }

    /**
     * @return string
     * @day 2020-03-30
     */
    public static function tableName(): string
    {
        return 'merchant_ad_coupon';
    }

    public function couponList() : HasMany
    {
        return $this->hasMany(CouponStocks::class, 'stock_id', 'stock_id');
    }

    public function couponInfo(): HasOne
    {
        return $this->hasOne(CouponStocks::class, 'stock_id', 'stock_id');
    }
}
