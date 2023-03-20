<?php


namespace crmeb\listens;


use app\common\model\applet\WxAppletSubmitAuditModel;
use app\common\repositories\wechat\OpenPlatformRepository;
use crmeb\interfaces\ListenerInterface;
use crmeb\services\TimerService;
use think\facade\Log;

class UpdateAppletSubmitAuditListen extends TimerService implements ListenerInterface
{
    protected string $name = '异步处理小程序提审流程:' . __CLASS__;
    /**
     * 异步处理小程序提审流程
     * @param $event
     * @author  wzq
     * @date    2023/3/8 21:07
     */
    public function handle($event): void
    {
        $this->tick(1000 * 60 * 2, function () {
            Log::info("异步处理小程序提审流程:start");
            // 查询待检测小程序
            $data = WxAppletSubmitAuditModel::getDB()
                ->where('status', WxAppletSubmitAuditModel::STATUS_AUDITING)
                ->where('detection_status', WxAppletSubmitAuditModel::DETECTION_STATUS_WAIT)
                ->field(['id', 'original_appid'])
                ->select()
                ->toArray();

            if (empty($data)) {
                return ;
            }
            Log::info("异步处理小程序提审流程:data".json_encode($data));
            $make = app()->make(OpenPlatformRepository::class);
            foreach($data as $item){
                $make->getCodePrivacyInfo($item['id'], $item['original_appid']);
            }
            Log::info("异步处理小程序提审流程:end");
        });
    }
}