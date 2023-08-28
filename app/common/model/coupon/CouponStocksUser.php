<?php

namespace app\common\model\coupon;

use app\common\model\BaseModel;
use app\common\model\user\User;
use think\model\relation\HasOne;

class CouponStocksUser extends BaseModel
{

    protected $schema = [
        'ad_id'       => 'int', //广告id
        'appid'       => 'varchar', //领券小程序appid
        'coupon_code' => 'varchar', //券编码
        'create_time' => 'timestamp', //
        'end_at'      => 'timestamp', //结束时间
        'coupon_id'   => 'int',
        'is_del'      => 'tinyint', //删除状态：0=未删除，1=已删除
        'mch_id'      => 'int', //领券商户 id
        'mer_id'      => 'int', //建券商户主键
        'sss'         => 'int', //
        'start_at'    => 'timestamp', //开始时间
        'stock_id'    => 'varchar', //优惠券批次id
        'uid'         => 'int', //优惠券所属用户
        'unionid'     => 'varchar', //unionid
        'use_time'    => 'timestamp', //
        'written_off' => 'tinyint', //是否核销：0=未核销，1=已核销

    ];

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
