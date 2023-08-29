<?php


namespace crmeb\jobs\Merchant;


use app\common\model\wechat\MerchantComplaintRequestLog;
use app\common\repositories\wechat\MerchantComplaintRepository;
use crmeb\interfaces\JobInterface;
use think\facade\Log;
use think\queue\Job;

class ComplaintNotifyIntoDBJob implements JobInterface
{

    /**
     * @param Job $job
     * @param array $data
     */
    public function fire($job, $data)
    {
        /** @var MerchantComplaintRepository $repos */
        $repos = app()->make(MerchantComplaintRepository::class);

        $res = $repos->notifyIntoDb($data);
        $id = $data['request_db_id'];
        if(isset($res['error'])){
            Log::info('微信支付商户投诉request job 失败:'.$res['error']);
            MerchantComplaintRequestLog::where('id', $id)->update(['queue_status' => 2]);
        }else{
            MerchantComplaintRequestLog::where('id', $id)->update(['queue_status' => 1]);
        }

        $job->delete();
    }

    public function failed($data)
    {
        // TODO: Implement failed() method.
    }
}