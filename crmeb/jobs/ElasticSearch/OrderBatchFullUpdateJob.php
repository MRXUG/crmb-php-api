<?php


namespace crmeb\jobs\ElasticSearch;


use app\common\repositories\merchant\DataCenter\OrderInElasticSearchRepository;
use crmeb\interfaces\JobInterface;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Log;
use think\queue\Job;

class OrderBatchFullUpdateJob implements JobInterface
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
        $timeQueue = $data['time_queue'] ?? "-1 day";
        $beginDate = date('Y-m-d H:i:s', strtotime($timeQueue));
        try {
            $repository->bulk($beginDate);
        } catch (\Exception $e) {
            Log::info('订单批量操作失败:'.__CLASS__.$e->getMessage().json_encode($data));
        }

        $job->delete();
    }

    public function failed($data)
    {
        // TODO: Implement failed() method.
    }

}