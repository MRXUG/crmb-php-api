<?php


namespace crmeb\jobs\Merchant;


use app\common\model\system\merchant\Merchant;
use crmeb\interfaces\JobInterface;
use crmeb\services\WechatService;
use think\facade\Log;
use think\queue\Job;

class UpdateWeChatComplaintUrlJob implements JobInterface
{
    const ActionCreate = 'create';
    const ActionUpdate = 'create';
    /**
     * 创建或更新 投诉通知回调
     * @param Job $job
     * @param array $data
     * @return void
     */
    public function fire($job, $data)
    {

        $oldMerchantConfig  = $data['oldMerchantConfig'];
        $action = $data['action'];
        $merId = $data['id'];
        $url = env('APP.HOST'). '/api/notice/wechat_complaint_notify/'.$merId;
        $merInfo = Merchant::getInstance()->where('mer_id', $merId)->find();

        Log::info('更新微信支付商户投诉回调URL:'.json_encode($data));
        $wechatService = WechatService::getMerPayObj($merId)->MerchantComplaint();
        if(!isDebug()){
            // 系统内部保证一个商户号只和一个mer_id绑定
            try {

                switch ($action){
                    case self::ActionCreate:
                        $wechatService->createNotification($url);
                        $merInfo->wechat_complaint_notify_url = $url;
                        $merInfo->wechat_complaint_notify_status = 1;
                        $merInfo->save();
                        break;
                    case self::ActionUpdate:
                        if(!empty($oldMerchantConfig) && $merInfo->wechat_complaint_notify_url){
                            $oldService = new WechatService($oldMerchantConfig);
                            $oldService->MerchantComplaint()->deleteNotification();
                        }
                        $wechatService->createNotification($url);
                        $merInfo->wechat_complaint_notify_url = $url;
                        $merInfo->wechat_complaint_notify_status = 1;
                        $merInfo->save();
                        break;
                    default:
                        break;
                }
            }catch (\Throwable $e){
                Log::info('更新微信支付商户投诉回调URL失败:'.$e->getMessage().':'.$e->getFile().":".$e->getLine());
            }

        }


        $job->delete();
    }

    public function failed($data)
    {
        // TODO: Implement failed() method.
    }

}