<?php

namespace app\common\model\platform;

use app\common\model\BaseModel;

class PlatformCouponReceive extends BaseModel
{

    public static function tablePk(): ?string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'platform_coupon_receive';
    }

    /**
     * 失效微信优惠券
     *
     * @param int $id
     * @return void
     */
    public static function destroyWxCouponStatus(int $id): void
    {
        PlatformCouponReceive::getInstance()->where('id', $id)->update([
            'wx_coupon_destroy' => 1,
            'update_time' => date("Y-m-d H:i:s")
        ]);
    }
}
