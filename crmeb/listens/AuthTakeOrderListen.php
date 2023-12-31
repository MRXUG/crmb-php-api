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


use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\store\order\StoreOrderStatusRepository;
use crmeb\interfaces\ListenerInterface;
use crmeb\jobs\OrderReplyJob;
use crmeb\services\TimerService;
use Swoole\Timer;
use think\facade\Log;
use think\facade\Queue;

class AuthTakeOrderListen extends TimerService implements ListenerInterface
{

    protected string $name = "自动收货：" . __CLASS__;

    public function handle($event): void
    {
        // 1000 * 60 * 60
        $this->tick(1000 * 60 * 5, function () {
            $storeOrderStatusRepository = app()->make(StoreOrderStatusRepository::class);
            $storeOrderRepository = app()->make(StoreOrderRepository::class);
            request()->clearCache();
            $timer = ((int)systemConfig('auto_take_order_timer')) ?: 15;
            $time = date('Y-m-d H:i:s', strtotime("- $timer day")); // change debug for test,should be day
            $ids = $storeOrderStatusRepository->getTimeoutDeliveryOrder($time);
            Log::info('本轮 自动收货 任务 查询到需要处理的订单ID: [' . implode(",", $ids) . "]");

            foreach ($ids as $id) {
                try {
                    $storeOrderRepository->takeOrder($id);
                    Queue::push(OrderReplyJob::class, $id);
                } catch (\Exception $e) {
                    Log::error('自动收货失败:' . $e->getMessage());
                }
            }
        });
    }
}
