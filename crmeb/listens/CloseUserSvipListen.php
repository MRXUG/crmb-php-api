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


namespace crmeb\listens;

use crmeb\interfaces\ListenerInterface;
use crmeb\jobs\CloseUserSvipJob;
use crmeb\services\TimerService;
use think\facade\Queue;

class CloseUserSvipListen extends TimerService implements ListenerInterface
{
    protected string $name = '关闭付费会员:' . __CLASS__;
    public function handle($event): void
    {
        $this->tick(1000 * 60 * 15, function () {
            request()->clearCache();
            Queue::push(CloseUserSvipJob::class,[]);
        });
    }
}
