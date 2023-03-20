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

// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace app\command;

use app\common\repositories\wechat\OpenPlatformRepository;
use EasySwoole\WeChat\Kernel\ServiceProviders;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Cache;
use think\facade\Log;

class testWechat extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('test:wechat')
            ->setDescription('测试微信：php think test:wechat');
    }

    /**
     * test
     * @param Input $input
     * @param Output $output
     * @return int|void|null
     * @author Qinii
     * @day 4/24/22
     */
    protected function execute(Input $input, Output $output)
    {
        sendMessageToWorkBot([
            'msg' => 'test',
            'file' => __FILE__,
            'line' => __LINE__
        ]);
//        $make = app()->make(OpenPlatformRepository::class);
//        $make->setCategorySetting('wx59aa89fa638c1c32');
    }
}
