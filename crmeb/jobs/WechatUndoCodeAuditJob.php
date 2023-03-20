<?php


namespace crmeb\jobs;


use app\common\repositories\wechat\OpenPlatformRepository;
use crmeb\interfaces\JobInterface;
use think\facade\Log;

class WechatUndoCodeAuditJob implements JobInterface
{

    public function fire($job, $data)
    {
        $_SERVER['x_request_id'] = $job->getJobId();
        Log::info('撤回代码审核job' . json_encode($data, JSON_UNESCAPED_UNICODE));
        app()->make(OpenPlatformRepository::class)->undoCodeAudit($data);
        $job->delete();
    }

    public function failed($data)
    {
        // TODO: Implement failed() method.
    }
}
