<?php

// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------


namespace app\common\model\store\coupon;


use app\common\model\BaseModel;
use app\common\model\system\merchant\Merchant;
use app\common\model\user\User;

/**
 * Class StoreCouponUser
 * @package app\common\model\store\coupon
 * @author xaboy
 * @day 2020-05-14
 */
class StoreCouponUser extends BaseModel
{

    protected $schema = [
        'coupon_id'      => 'int', //兑换的项目id
        'coupon_price'   => 'decimal', //优惠券的面值
        'coupon_title'   => 'varchar', //优惠券名称
        'coupon_user_id' => 'int', //优惠券发放记录id
        'create_time'    => 'timestamp', //优惠券创建时间
        'end_time'       => 'timestamp', //优惠券结束时间
        'is_fail'        => 'tinyint', //是否有效
        'mer_id'         => 'int', //商户 id
        'send_id'        => 'int', //批量发送 id
        'start_time'     => 'timestamp', //优惠券开启时间
        'status'         => 'tinyint', //状态（0：未使用，1：已使用, 2:已过期）
        'type'           => 'varchar', //获取方式(receive:自己领取 send:后台发送  give:满赠  new:新人 buy:买赠送)
        'uid'            => 'int', //优惠券所属用户
        'use_min_price'  => 'decimal', //最低消费多少金额可用优惠券
        'use_time'       => 'timestamp', //使用时间

    ];
    /**
     * @return string
     * @author xaboy
     * @day 2020-03-30
     */
    public static function tablePk(): string
    {
        return 'coupon_user_id';
    }

    /**
     * @return string
     * @author xaboy
     * @day 2020-03-30
     */
    public static function tableName(): string
    {
        return 'store_coupon_user';
    }

    public function user()
    {
        return $this->hasOne(User::class, 'uid', 'uid');
    }

    public function coupon()
    {
        return $this->hasOne(StoreCoupon::class, 'coupon_id', 'coupon_id');
    }

    public function product()
    {
        return $this->hasMany(StoreCouponProduct::class, 'coupon_id', 'coupon_id');
    }

    public function merchant()
    {
        return $this->hasOne(Merchant::class, 'mer_id', 'mer_id');
    }
}
