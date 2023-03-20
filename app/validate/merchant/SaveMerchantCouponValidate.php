<?php


namespace app\validate\merchant;

use think\Validate;

/**
 * 商家券-建券验证器
 */
class SaveMerchantCouponValidate extends Validate
{
    /**
     * @var bool
     */
    protected $failException = true;

    /**
     * @var string[]
     */
    protected $rule = [
        'stock_name|优惠券名称'                      => 'max:21',
        'type|优惠券类型'                            => 'in:1,2',
        'scope|适用商品'                             => 'in:1,2',
        'goods_list|适用商品'                        => 'array', // 关联商品
        'discount_num|优惠券面值'                    => 'integer|min:1',
        'transaction_minimum|使用门槛'               => 'integer|min:1',
        'wait_days_after_receive|领券后立即生效天数' => 'min:0',
        'available_day_after_receive|领券后N天有效'  => 'min:1',
        'available_begin_time|领券开始时间'          => 'date',
        'available_end_time|领券结束时间'            => 'date|after:available_begin_time',
        'max_coupons|限量最大值'                     => 'integer|min:1|max:1000000000',
        'max_coupons_per_user|每人限量'              => 'integer|min:1|max:100',
        'is_limit|是否限量'                          => 'in:0,1',
        'is_user_limit|每人是否限量'                 => 'in:0,1',
        'type_date|使用有效期tab'                    => 'integer|in:1,2,3',
    ];
}