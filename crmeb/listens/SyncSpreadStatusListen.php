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


use app\common\repositories\user\UserRepository;
use crmeb\interfaces\ListenerInterface;
use crmeb\services\TimerService;
use crmeb\services\YunxinSmsService;
use Swoole\Timer;

class SyncSpreadStatusListen extends TimerService implements ListenerInterface
{
    protected string $name = '分销员绑定关系到期状态:' . __CLASS__;
    public function handle($event): void
    {
        $this->tick(1000 * 60 * 30, function () {
            request()->clearCache();
            app()->make(UserRepository::class)->syncSpreadStatus();
        });
    }
}
