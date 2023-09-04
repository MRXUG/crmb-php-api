<?php


namespace crmeb\jobs;


use app\common\dao\store\order\StoreOrderDao;
use app\common\dao\system\merchant\MerchantAdDao;
use app\common\model\applet\AppletsTx;
use crmeb\interfaces\JobInterface;
use crmeb\services\ads\Action\CompleteOrder;
use crmeb\services\ads\Action\ViewContent;
use think\facade\Log;

class AdvertisingReportingViewJob implements JobInterface
{
    public function fire($job, $data)
    {
        Log::info("gdt-view-queue".json_encode($data));
        $result = (new ViewContent($data['appid'],$data['unionid'], $data['gdt_vid']))
            ->setTimestamp($data['timestamp'])
            ->handle();
        if ($result['code'] != 0) {
            sendMessageToWorkBot([
                'module' => '广告落地页',
                'type'   => 'error',
                'msg'    => '回传失败' . json_encode($result),
            ]);
        }
        Log::info("gdt-view-queue-success".json_encode($data));
        $job->delete();
    }

    public function failed($data)
    {
        // TODO: Implement failed() method.
    }
}
