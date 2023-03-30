<?php

namespace crmeb\jobs;

use app\common\model\store\order\StoreRefundOrder;
use app\common\model\store\RefundTask;
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
                $refundOrder = StoreRefundOrder::getDB()->where('refund_order_id', $refundOrderTask->getAttr('refund_order_id'))->find();
                if (!$refundOrder) { $job->delete();return; }
                # 发起查询接口
                $res = MiniProgramService::create($refundOrderTask->getAttr('mer_id'), $refundOrderTask->getAttr('app_id'))
                    ->paymentService()->queryRefund($refundOrder->getAttr('refund_order_sn'), 'out_refund_no');

                if ($res->return_code == 'FAIL') {
                    $this->profitSharingErrHandler($refundOrderTask, ['发起退款失败 ' . $res->return_msg ?? '']);
                    return;
                }

                if ($res->err_code == 'SYSTEMERROR') {
                    $job->delete();
                    Queue::push(self::class, $data);
                    return;
                }

                if (isset($res->err_code)) {
                    $this->profitSharingErrHandler($refundOrderTask, ['发起退款失败 错误码:' . $res->err_code_des ?? '']);
                    return;
                }

                $refundOrder->setAttr('status', 3);
                $refundOrder->save();
            });
        } catch (Exception|Throwable|ValueError $e) {
            Log::error("确认提款成功队列出错 id:{$data['refund_task_id']}");
        }


    }

    public function failed($data)
    {

    }

    /**
     * 返回错误集中处理
     *
     * @param RefundTask $task
     * @param array $errArr
     * @return bool
     */
    private function profitSharingErrHandler(RefundTask $task, array $errArr): bool
    {
        # 如果没有错误的话那么返回false继续向下执行
        if (empty($errArr)) return false;
        # 解析先前存在的错误
        $newTask = clone $task;
        $newTask->setAttr('err_msg',  implode(";", array_merge(explode(";", $newTask->getAttr('err_msg')), $errArr)));
        $newTask->save();
    }
}
