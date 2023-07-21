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
use crmeb\jobs\LiveStatusCheckJob;
use crmeb\services\TimerService;

class LiveStatusCheckListen extends TimerService implements ListenerInterface
{
    protected string $name = "队列存活检查";

    public function handle($params): void
    {
        $this->tick(1000 * 60 * 10, function () {
            $uniqueId = mini_unique_id(4);
            \think\facade\Queue::push(LiveStatusCheckJob::class, $uniqueId, $queue = null);
        });
    }
}
