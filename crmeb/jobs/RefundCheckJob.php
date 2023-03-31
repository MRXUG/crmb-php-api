<?php

namespace crmeb\jobs;

use app\common\dao\store\order\StoreRefundOrderDao;
use app\common\model\store\order\StoreRefundOrder;
use app\common\model\store\RefundTask;
use app\common\repositories\store\order\StoreRefundOrderRepository;
use crmeb\interfaces\JobInterface;
use crmeb\services\MiniProgramService;
use think\facade\Db;
use think\facade\Log;
use think\facade\Queue;
use think\queue\Job;
use Exception;
use Throwable;
use ValueError;

/**
 * 检查退款是否成功
 *
 * @author x
 */
class RefundCheckJob implements JobInterface
{

    /**
     * @param Job $job
     * @param array{refund_task_id:string} $data
     * @throws null
     * @return void
     */
    public function fire($job, $data)
    {
        try {
            Db::transaction(function () use($job, $data) {
                # 查询需要处理的模型
                $refundOrderTask = RefundTask::getDB()->where('refund_task_id', $data['refund_task_id'])->find();
                if (!$refundOrderTask) { $job->delete();return; }
                # 获取退款订单模型
                /** @var StoreRefundOrderDao $refundOrderDao */
                $refundOrderDao = app()->make(StoreRefundOrderDao::class);
                /** @var StoreRefundOrder $refundOrder */
                $refundOrder = $refundOrderDao->getWhere(['refund_order_id' => $refundOrderTask->getAttr('refund_order_id')], '*', ['refundProduct.product']);
                if (!$refundOrder) { $job->delete();return; }
                # 发起查询接口
                $res = MiniProgramService::create($refundOrderTask->getAttr('mer_id'), $refundOrderTask->getAttr('app_id'))
                    ->paymentService()->queryRefund($refundOrder->getAttr('refund_order_sn'), 'out_refund_no');

                if ($res->return_code == 'FAIL') {
                    $refundOrderTask->profitSharingErrHandler(['发起退款失败 ' . $res->return_msg ?? '']);
                    return;
                }

                if ($res->err_code == 'SYSTEMERROR') {
                    $job->delete();
                    Queue::push(self::class, $data);
                    return;
                }

                if (isset($res->err_code)) {
                    $refundOrderTask->profitSharingErrHandler(['发起退款失败 错误码:' . $res->err_code_des ?? '']);
                    return;
                }

                $refundOrder->setAttr('status', 3);
                $refundOrder->save();
                /** @var StoreRefundOrderRepository $refundOrderRep */
                $refundOrderRep = app()->make(StoreRefundOrderRepository::class);
                $refundOrderRep->refundAfter($refundOrder);
            });
        } catch (Exception|Throwable|ValueError $e) {
            Log::error("确认提款成功队列出错 id:{$data['refund_task_id']}");
        }


    }

    public function failed($data)
    {

    }
}
