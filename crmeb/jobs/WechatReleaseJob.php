<?php


namespace crmeb\jobs;


use app\common\repositories\wechat\OpenPlatformRepository;
use crmeb\interfaces\JobInterface;
use think\facade\Log;

class WechatReleaseJob implements JobInterface
{

    public function fire($job, $data)
    {
        Log::info('发布已通过审核的小程序job' . json_encode($data, JSON_UNESCAPED_UNICODE));
        app()->make(OpenPlatformRepository::class)->release($data);
        $job->delete();
    }

    public function failed($data)
    {
        // TODO: Implement failed() method.
    }
}
