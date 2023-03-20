<?php
namespace crmeb\jobs;
use app\common\repositories\store\order\StoreOrderRepository;
use crmeb\interfaces\JobInterface;
use think\facade\Log;

/**
 * 处理商户收益
 */
class HandleMerchantProfit implements JobInterface
{
    public function fire($job, $data)
    {
        Log::info('执行开始：订单付后处理商户收益');
        try{
            /* @var StoreOrderRepository $orderRepo */
            $orderRepo = app()->make(StoreOrderRepository::class);
            $orderRepo->handleProfit($data);
            $job->delete();
        }catch (\Exception $e){
            sendMessageToWorkBot([
                'module' => '商户收益',
                'msg'    => '处理商户收益出现异常：'.$e->getMessage(),
                'params' => json_encode($data),
                'file'    => $e->getFile(),
                'line'    => $e->getLine()
            ]);
            throw new \Exception($e);
        }
        Log::info('执行结束：订单付后处理商户收益');
    }
    public function failed($data)
    {
        // TODO: Implement failed() method.
    }
}