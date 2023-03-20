<?php

namespace crmeb\jobs;

use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\system\merchant\MerchantGoodsPaymentRepository;
use crmeb\interfaces\JobInterface;
use think\facade\Log;

/**
 * 记录货款
 */
class HandleGoodsPaymentJob implements JobInterface
{
    public function fire($job, $data)
    {
        Log::info('执行开始：订单支付成功后记录货款');
        try {
            /* @var MerchantGoodsPaymentRepository $orderRepo */
            $orderRepo = app()->make(MerchantGoodsPaymentRepository::class);
            $orderRepo->saveGoodsPayment($data);
            $job->delete();
        } catch (\Exception $e) {
            Log::error('记录货款出现异常,data:'.json_encode($data));
            sendMessageToWorkBot([
                'module'  => '货款与服务费',
                'msg'     => '记录货款出现异常：'.$e->getMessage(),
                'params'  => json_encode($data),
                'file'    => $e->getFile(),
                'line'    => $e->getLine()
            ]);
            throw new \Exception($e);
        }
        Log::info('执行结束：订单支付成功后记录货款');
    }

    public function failed($data)
    {
        // TODO: Implement failed() method.
    }
}