<?php


namespace crmeb\jobs\Merchant;


use app\common\model\store\order\StoreOrder;

use app\common\model\system\merchant\Merchant;
use app\common\repositories\merchant\DataCenter\OrderInElasticSearchRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use crmeb\interfaces\JobInterface;
use think\facade\Log;
use think\queue\Job;

class UpdateWeChatComplaintUrlJob implements JobInterface
{
    /**
     * 创建或更新 投诉通知回调
     * @param Job $job
     * @param array $data
     * @return void
     */
    public function fire($job, $data)
    {

        $oldMerchantId  = $data['oldMerchantId'];
        $merchantId = $data['merchantId'];
        $merId = $data['id'];
        $url = env('APP.HOST'). '/api/notice/wechat_complaint_notify/'.$merId;
        $merInfo = Merchant::getInstance()->where('mer_id', $merId)->find();

        if(!isDebug()){
            // 系统内部保证一个商户号只和一个mer_id绑定
            if($oldMerchantId != $merchantId){
                //商户号更新，删除旧商户号回调，新增新商户号
            }elseif ($url != $merInfo->url){
                if(empty($merInfo->url)){
                    //post
                }else{
                    //put
                }
            }

        }


        $job->delete();
    }

    public function failed($data)
    {
        // TODO: Implement failed() method.
    }

}