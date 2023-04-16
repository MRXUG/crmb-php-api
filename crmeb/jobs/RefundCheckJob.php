<?php

namespace crmeb\jobs;

use app\common\dao\store\order\StoreOrderDao;
use app\common\dao\store\order\StoreRefundOrderDao;
use app\common\model\store\order\OrderFlow;
use app\common\model\store\order\StoreRefundOrder;
use app\common\model\store\RefundTask;
use app\common\repositories\store\order\OrderFlowRepository;
use app\common\repositories\store\order\StoreRefundOrderRepository;
use app\common\repositories\store\order\StoreRefundStatusRepository;
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
                /** @var RefundTask $refundOrderTask */
                $refundOrderTask = RefundTask::getDB()->where('refund_task_id', $data['refund_task_id'])->find();
                if (!$refundOrderTask) { $job->delete();return; }
                # 发起退款 2 分钟后查询状态
                if (strtotime($refundOrderTask->getAttr('refund_time')) + 60 * 2 > time()) {
                    $job->delete();
                    Queue::later(60, RefundCheckJob::class, $data);
                    return;
                }
                # 获取退款订单模型
                /** @var StoreRefundOrderDao $refundOrderDao */
                $refundOrderDao = app()->make(StoreRefundOrderDao::class);
                /** @var StoreRefundOrder $refundOrder */
                $refundOrder = $refundOrderDao->getWhere(['refund_order_id' => $refundOrderTask->getAttr('refund_order_id')], '*', ['refundProduct.product']);
                if (!$refundOrder) { $job->delete();return; }
                # 发起查询接口
                $res = MiniProgramService::create($refundOrderTask->getAttr('mer_id'), $refundOrderTask->getAttr('app_id'))
                    ->paymentService()
                    ->queryRefund($refundOrder->getAttr('refund_order_sn'), 'out_refund_no');

                if ($res->return_code == 'FAIL') {
                    $refundOrderTask->profitSharingErrHandler([$refundOrder->getAttr('refund_order_sn') . '发起退款失败 ' . ($res->return_msg ?? '')]);
                    return;
                }

                if (isset($res->err_code)) {
                    if ($res->err_code == 'SYSTEMERROR') {
                        $job->delete();
                        Queue::later(15, RefundCheckJob::class, $data);
                        return;
                    }
                    $refundOrderTask->profitSharingErrHandler(['发起退款失败 错误码: ' . ($res->err_code_des ?? '')]);
                    return;
                }

                $refundOrder->setAttr('status', 3);
                $refundOrder->save();
                /** @var StoreRefundOrderRepository $refundOrderRep */
                $refundOrderRep = app()->make(StoreRefundOrderRepository::class);
                /** @var StoreRefundStatusRepository $statusRepository */
                $statusRepository = app()->make(StoreRefundStatusRepository::class);
                $statusRepository->status(
                    $refundOrderTask->getAttr('refund_order_id'),
                    $statusRepository::CHANGE_REFUND_PRICE,
                    '退款成功'
                );
                $this->orderFlow($refundOrderTask);
                /** @var StoreOrderDao $orderDao */
                $orderDao = app()->make(StoreOrderDao::class);
                $orderDao->updateOrderStatus($refundOrder->getAttr('order_id'), -1);
                $refundOrderRep->refundAfter($refundOrder);
            });
        } catch (Exception|Throwable|ValueError $e) {
            Log::error("确认提款成功队列出错 id:{$data['refund_task_id']}");
        }
        $job->delete();
    }

    public function orderFlow (RefundTask $refundTask)
    {
        $amountList = OrderFlow::getInstance()->where([
            ['order_sn', '=', $refundTask->getAttr('order_sn')],
            ['type', '=', 1]
        ])->column('amount');

        $amount = 0;
        foreach ($amountList as $item)  $amount = bcadd($item, $amount, 2);

        if ($amount == 0) {
            app()->make(OrderFlowRepository::class)->refundOrderFlowWrite([
                'amount' => '-' . $amount,
                'type' => OrderFlow::FLOW_TYPE_OUT,
                'create_time' => date('Y-m-d H:i:s'),
                'mer_id' => $refundTask->getAttr('mer_id'),
                'mch_id' => 0,
                'order_sn' => $refundTask->getAttr('order_sn'),
                'remark' => OrderFlow::SALE_AFTER_REFUND_CN
            ]);
        }
    }

    public function failed($data)
    {

    }
}
