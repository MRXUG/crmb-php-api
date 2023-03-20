<?php
namespace crmeb\jobs;
use app\common\repositories\store\order\StoreOrderRepository;
use crmeb\interfaces\JobInterface;
use think\facade\Log;

/**
 * 处理商户-客户绑定关系
 */
class HandleBindingJob implements JobInterface
{
    public function fire($job, $data)
    {
        Log::info('执行开始：订单付后处理绑定关系');
        try{
            /* @var StoreOrderRepository $orderRepo */
            $orderRepo = app()->make(StoreOrderRepository::class);
            $orderRepo->handleBinding($data);
            $job->delete();
        }catch (\Exception $e){
            Log::error('处理绑定关系出现异常：'.$e->getMessage());
            sendMessageToWorkBot([
                'module'  => '商户-用户绑定',
                'msg'     => '处理绑定关系出现异常：'.$e->getMessage(),
                'params'  => json_encode($data),
                'file'    => $e->getFile(),
                'line'    => $e->getLine()
            ]);
            throw new \Exception($e);
        }
        Log::info('执行结束：订单付后处理绑定关系');
    }
    public function failed($data)
    {
        // TODO: Implement failed() method.
    }
}