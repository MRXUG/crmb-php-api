<?php


namespace crmeb\jobs;


use app\common\repositories\wechat\OpenPlatformRepository;
use crmeb\interfaces\JobInterface;
use think\facade\Log;

class WechatSubmitAuditJob implements JobInterface
{

    public function fire($job, $data)
    {
        Log::info('上传代码并生成体验版job' . json_encode($data, JSON_UNESCAPED_UNICODE));
        app()->make(OpenPlatformRepository::class)->uploadCode($data);
        $job->delete();
    }

    public function failed($data)
    {
        // TODO: Implement failed() method.
    }
}
