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
        $this->tick(1000 * 60 * 60 * 4 , function () {
            try {
                Queue::push(OrderBatchFullUpdateJob::class,[]);
            } catch (\Exception $e) {

            }
        });
        return;
    }
}