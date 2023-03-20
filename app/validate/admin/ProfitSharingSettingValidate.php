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

class ProfitSharingSettingValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        'profit_sharing_natural_flow|自然流量分佣' => 'require',
        'profit_sharing_advertising_flow|广告流量分佣' => 'require',
        'profit_sharing_return_flow_rate|回流流量分佣' => 'require',
        'profit_sharing_advertising_switch|广告补偿开关' => 'require|in:0,1',
        'profit_sharing_locking_duration|客户锁定时长' => 'require',
        'profit_sharing_natural_flow_profit|自然流量收益' => 'require',
        'profit_sharing_advertising_flow_deposit|广告流量押款' => 'require',
    ];

}
