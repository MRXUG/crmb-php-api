<?php
/**
 * @user: BEYOND 2023/3/10 19:48
 */

namespace app\validate\api;

use think\Validate;

class ReceiveCouponValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        'coupon_code|券编码' => 'require|min:1',
        'ad_id|广告ID' => 'require|min:1',
    ];
}