<?php

namespace crmeb\jobs;


use app\common\repositories\coupon\ChangeBatchStatusRepository;
use crmeb\interfaces\JobInterface;

class ChangeBatchStatusJob implements JobInterface
{

    public function fire($job, $data)
    {
        app()->make(ChangeBatchStatusRepository::class)->changeStatus($data['coupon_stocks_id'], $data['event']);
        $job->delete();
    }

    public function failed($data)
    {
        // TODO: Implement failed() method.
    }
}