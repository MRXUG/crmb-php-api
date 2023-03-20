<?php
/**
 * @user: BEYOND 2023/3/5 21:46
 */

namespace app\validate\api;

use think\Validate;

class UseCouponValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        'stock_id|批次id' => 'require|min:1',
        'coupon_code|券编码' => 'require|min:1',
    ];
}