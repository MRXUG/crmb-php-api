<?php

namespace app\common\model\system\merchant;

use app\common\model\BaseModel;
use think\model\relation\HasMany;

class MerchantAd extends BaseModel
{
    /**
     * @return string
     * @day 2020-03-30
     */
    public static function tablePk(): string
    {
        return 'ad_id';
    }

    /**
     * @return string
     * @day 2020-03-30
     */
    public static function tableName(): string
    {
        return 'merchant_ad';
    }

    public function couponIds() : HasMany
    {
        return $this->hasMany(MerchantAdCoupon::class, 'ad_id', 'ad_id');
    }

    public function getMultistepDiscountAttr($value)
    {
        return $value ? json_decode($value) : [];
    }
}