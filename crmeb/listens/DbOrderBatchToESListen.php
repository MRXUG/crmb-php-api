<?php


namespace crmeb\listens;


use crmeb\interfaces\ListenerInterface;
use crmeb\jobs\ElasticSearch\OrderBatchFullUpdateJob;
use crmeb\services\TimerService;
use think\facade\Log;
use think\facade\Queue;

class DbOrderBatchToESListen extends TimerService  implements ListenerInterface
{

    public function handle($event): void
    {
        $this->tick(1000 * 60 * 15 , function () {
            try {
                //每十五分钟同步一次订单数据
                Queue::push(OrderBatchFullUpdateJob::class,["time_queue" => "-1 hour"]);
            } catch (\Exception $e) {

            }
        });
        return;
    }
}