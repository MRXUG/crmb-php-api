<?php


namespace crmeb\jobs\ElasticSearch;


use app\common\model\store\order\StoreOrder;
use app\common\repositories\merchant\DataCenter\OrderInElasticSearchRepository;
use crmeb\interfaces\JobInterface;
use think\queue\Job;

class OrderInsertJob implements JobInterface
{
    /**
     * @param Job $job
     * @param StoreOrder $order
     * @return void
     */
    public function fire($job, $order)
    {

        /** @var OrderInElasticSearchRepository $repository */
        $repository = app()->make(OrderInElasticSearchRepository::class);
        $repository->create($order);

        $job->delete();
    }

    public function failed($data)
    {
        // TODO: Implement failed() method.
    }

}