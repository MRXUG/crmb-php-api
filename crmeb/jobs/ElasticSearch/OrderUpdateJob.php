<?php


namespace crmeb\jobs\ElasticSearch;


use app\common\model\store\order\StoreOrder;

use app\common\repositories\merchant\DataCenter\OrderInElasticSearchRepository;
use crmeb\interfaces\JobInterface;
use think\facade\Log;
use think\queue\Job;

class OrderUpdateJob implements JobInterface
{
    /**
     * @param Job $job
     * @param array $data
     * @return void
     */
    public function fire($job, $data)
    {

        /** @var OrderInElasticSearchRepository $repository */
        $repository = app()->make(OrderInElasticSearchRepository::class);
        $orderIds = $data['orderIds'];
        $updateColumn = isset($data['update_column']) ? $data['update_column'] : [];
        try {
            if(count($orderIds) == 1){
                $repository->update($orderIds[0], $updateColumn);
            }else{
                $repository->batchUpdate($orderIds);
            }
        }catch (\Exception $e){
            Log::info('订单批量更新失败:'.__CLASS__.$e->getMessage().json_encode($data));
        }


        $job->delete();
    }

    public function failed($data)
    {
        // TODO: Implement failed() method.
    }

}