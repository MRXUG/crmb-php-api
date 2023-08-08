<?php


namespace crmeb\listens;


use crmeb\interfaces\ListenerInterface;
use crmeb\jobs\ElasticSearch\OrderBatchFullUpdateJob;
use crmeb\jobs\ElasticSearch\OrderUpdateJob;
use think\facade\Queue;

class OrderAfterUpdateListen implements ListenerInterface
{
    public function handle($params): void
    {
        Queue::push(OrderUpdateJob::class, $params);
    }
}