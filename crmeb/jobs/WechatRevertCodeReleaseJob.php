<?php


namespace crmeb\jobs;


use app\common\repositories\wechat\OpenPlatformRepository;
use crmeb\interfaces\JobInterface;
use think\facade\Log;

class WechatRevertCodeReleaseJob implements JobInterface
{

    public function fire($job, $data)
    {
        Log::info('小程序版本回退job' . json_encode($data, JSON_UNESCAPED_UNICODE));
        app()->make(OpenPlatformRepository::class)->revertCodeRelease($data);
        $job->delete();
    }

    public function failed($data)
    {
        // TODO: Implement failed() method.
    }
}
