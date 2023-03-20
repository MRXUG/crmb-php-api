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


namespace crmeb\services;


use Swoole\Timer;
use think\facade\Log;

class TimerService
{
    protected string $name = "unknown name";

    public function tick($limit, $fn)
    {
        Timer::tick($limit, function () use ($fn) {
            \think\facade\Log::info("开始执行计划任务:" . $this->name);
            try {
                $_SERVER['x_request_id'] = mini_unique_id();
                $fn();
            } catch (\Throwable $e) {
                $msg = '定时器报错[' . class_basename($this) . ']';
                Log::error($msg);
                sendMessageToWorkBot([
                    'msg' => $msg,
                    '`exception_message`' => $e->getMessage(),
                    '`file`' => $e->getFile(),
                    '`line`' => $e->getLine(),
                ]);
            }
        });
    }
}
