<?php



namespace crmeb\jobs\ElasticSearch;


use app\common\repositories\user\UserVisitLogRepository;
use crmeb\interfaces\JobInterface;
use think\facade\Log;
use think\queue\Job;

class UserVisitLogJob implements JobInterface
{

    /**
     * @param Job $job
     * @param array app\validate\Elasticsearch\UserVisitLogValidate::rule
     * @return void
     */
    public function fire($job, $data)
    {
        app()->make(UserVisitLogRepository::class)->create($data);

        $job->delete();
    }

    public function failed($data)
    {
        // TODO: Implement failed() method.
    }
}
