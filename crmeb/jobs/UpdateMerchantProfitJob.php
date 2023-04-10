<?php
namespace crmeb\jobs;

use app\common\dao\system\merchant\MerchantDao;
use app\common\repositories\system\merchant\MerchantProfitRecordRepository;
use crmeb\interfaces\JobInterface;
use think\facade\Log;

class UpdateMerchantProfitJob implements JobInterface
{

    public function fire($job, $data)
    {
        Log::info("执行开始：处理商户收益");
            try {

                //查询所有商户
                $merchantList = MerchantDao::getMerchantAllIds();
                if (empty($merchantList)){
                    Log::info("执行结束：处理商户收益(未查询到有商户)");
                    return;
                }

                foreach ($merchantList as $k=>$v){
                    /**
                     * @var MerchantProfitRecordRepository $repo
                     */
                    $repo = app()->make(MerchantProfitRecordRepository::class);
                    $repo->setRecordsValidAndUpdateProfit($v);
                }

            } catch (\Exception $e) {
                Log::error('更新商户收益出错：'.$e->getMessage());
                sendMessageToWorkBot([
                    'module' => '商户收益',
                    'msg'    => '处理商户收益出现异常：'.$e->getMessage(),
                    'file'   => $e->getFile(),
                    'line'   => $e->getLine()
                ]);
            }
            $job->delete();
            Log::info("执行结束：处理商户收益");
    }

    public function failed($data)
    {
        // TODO: Implement failed() method.
    }
}