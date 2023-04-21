<?php

namespace crmeb\jobs;

use crmeb\interfaces\JobInterface;
use crmeb\utils\platformCoupon\RefreshPlatformCouponProduct;
use think\queue\Job;

class RefreshPlatformCoupon implements JobInterface
{

    /**
     * @param Job $job
     * @param $data
     * @return void
     */
    public function fire($job, $data)
    {
        RefreshPlatformCouponProduct::run();
        $job->delete();
    }

    public function failed($data)
    {

    }
}
