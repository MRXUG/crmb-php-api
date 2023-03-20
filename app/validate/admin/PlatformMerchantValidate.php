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


namespace app\validate\admin;


use think\Validate;

class PlatformMerchantValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        'merchant_id|商户id' => 'unique:platform_merchant',
        'key|key'          => 'require',
        'v3_key'           => 'require',
        'serial_no|序列号'  => 'require',
        'mer_name|商户简称'  => 'require',
        'cert_path|cert'   => 'require',
        'key_path|key'     => 'require',
    ];
}
