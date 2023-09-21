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


namespace app\validate\api;

use think\Validate;

class FeedbackValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        'type|类型' => 'require',
        'mer_id|商户id' => 'integer',
        'images|图片' => 'array|max:6',
        'realname|姓名' => 'alphaNum|max:24',
        'contact|联系方式' => 'checkContact',
        'order_id|订单ID' => 'integer|egt:0',
        'order_sn|订单编号' => 'alphaNum|max:32'
    ];

    protected function checkContact($val)
    {
        if ($this->regex($val, 'mobile') || $this->filter($val, 'email'))
            return true;
        else
            return '请输入正确的联系方式';
    }
}
