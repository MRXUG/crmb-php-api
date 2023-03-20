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

class AppletValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        'subject_id|小程序主体id' => 'require',
        'name|小程序名称' => 'require',
        'original_id|小程序原始ID' => 'require',
        'original_appid|小程序APPID' => 'require',
        'original_appsecret|小程序APPSECRET' => 'require'
    ];

}
