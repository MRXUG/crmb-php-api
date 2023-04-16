<?php

namespace crmeb\listens;

use app\common\dao\store\order\StoreRefundOrderDao;
use app\common\dao\store\RefundTaskDao;
use app\common\model\delivery\DeliveryProfitSharingStatus;
use app\common\model\store\RefundTask;
use crmeb\interfaces\ListenerInterface;
use crmeb\jobs\RefundCheckJob;
use crmeb\services\MiniProgramService;
use crmeb\services\TimerService;
use crmeb\services\WechatService;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;
use Exception;
use think\facade\Queue;
use ValueError;
use Throwable;

class OrderRefundListen extends TimerService implements ListenerInterface
{

    protected string $name = '订单退款处理-' . __CLASS__;

    private int $reNumber = 0;

    public function handle($event): void
    {
        # 每半分钟检测一次
        $this->tick(1000 * 15, function () {
            Log::info("开始运行 {$this->name} ". date("Y-m-d H:i:s"));
            try {
                Db::transaction(function () {
                    $nowTime = time();
                    # 查询获取任务信息·
                    /** @var RefundTask[] $task */
                    $task = RefundTask::getDB()->where('status', 0)->select();
                    if (empty($task)) return;
                    # 循环调用
                    foreach ($task as $item) {
                        $this->runner($item);
                    }
                });
            } catch (Exception|ValueError|Throwable $e) {
                Log::error("运行出错 {$this->name} ". date("Y-m-d H:i:s") . $e->getMessage() . '   [[' . serialize($e->getTrace()) . ']]');
            }
            Log::info("运行结束 {$this->name} ". date("Y-m-d H:i:s"));
        });
    }

    /**
     * @param RefundTask $task
     * @return void
     * @throws null
     */
    private function runner (RefundTask $task): void
    {
        # 将参数解析出来
        $param = json_decode($task->getAttr('param'), true);
        # 收集失败原因
        $errArr = [];
        # 收集还需要检测的
        $newParam = [];
        # 循环进行查询
        foreach ($param as $item) {
            # 调用查询分账回退结果
            $res = WechatService::getMerPayObj($item['merId'], $item['appId'])
                ->profitSharing()
                ->profitSharingReturnResult($item['outReturnNo'], $item['outOrderNo']);
            # 回退结果
            $profitSharingStatus = DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_ING;
            switch ($res['result']) {
                case 'SUCCESS':
                    $profitSharingStatus = DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_SUCCESS;
                    break;
                case 'FAILED':
                    $profitSharingStatus = DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_FAIL;
                    $errArr[] = $this->errorCode($res['fail_reason'], $item['returnMchId']);
                    break;
                default:
                    $newParam[] = $item;
                    break;
            }
            # 将状态更新到数据库
            DeliveryProfitSharingStatus::getDB()->where([
                ['order_sn', '=', $item['outOrderNo']],
                ['mch_id', '=', $item['returnMchId']]
            ])->update([
                'profit_sharing_status' => $profitSharingStatus
            ]);
        }
        # 处理错误信息
        if ($task->profitSharingErrHandler($errArr)) return;
        # 将处理后的param存储
        $this->saveTaskParam($task, $newParam);
        # 判断数据库中数据是否还存在未退回的
        if (
            DeliveryProfitSharingStatus::getDB()
                ->where('order_id', $task->getAttr('order_id'))
                ->where('profit_sharing_status', DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_ING)
                ->count() > 0
        ) return;
        # 走到此处说明已经没有等待退回分账的状态了 判断该订单是否还存在出现错误未巡回 如果存在的话不调用退回到用户
        if (
            DeliveryProfitSharingStatus::getDB()
                ->where('order_id', $task->getAttr('order_id'))
                ->where('profit_sharing_status', DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_FAIL)
                ->count() == 0
        ) {
            $this->refundToUser($task);
        }
        # 更新此任务为完成状态
        $task->setAttr('status', 1);
        $task->save();
    }

    /**
     * @param RefundTask $task
     * @return void
     * @throws null
     */
    public function refundToUser(RefundTask $task): void
    {
        # 清空错误信息
        $task->setAttr('err_msg', '');
        # 获取微信操作对象
        $weChat = WechatService::getMerPayObj($task->getAttr('mer_id'), $task->getAttr('app_id'));
        # 解冻资金
        try {
            $weChat->profitSharing()->profitSharingUnfreeze([
                'transaction_id' => $task->getAttr('transaction_id'),
                'out_order_no' => $task->getAttr('order_sn'),
                'description' => '用户退款解冻全部剩余资金',
            ]);
        } catch (Exception $e) {
            $task->profitSharingErrHandler(['解冻分账失败' . $e->getMessage()]);
            return;
        }
        /** @var StoreRefundOrderDao $refundOrderResp */
        $refundOrderResp = app()->make(StoreRefundOrderDao::class);
        $refundOrder = $refundOrderResp->getWhere(['refund_order_id' => $task->getAttr('refund_order_id')], "*", ['order']);
        # 发起退款
        $orderNo = $refundOrder->order->pay_order_sn;
        $refundId = $refundOrder->refund_order_sn;
        $payPrice = $refundOrder->order->groupOrder->pay_price;
        $refundPrice = $refundOrder->refund_price;

        $res = MiniProgramService::create($task->getAttr('mer_id'), $task->getAttr('app_id'))
            ->refund(
                $orderNo,
                $refundId,
                floatval(bcmul($payPrice, 100, 0)),
                floatval(bcmul($refundPrice, 100, 0)),
                null,
                '',
                'out_trade_no',
                'REFUND_SOURCE_UNSETTLED_FUNDS'
            );
        # 处理退款失败
        if ($res->return_code == 'FAIL') {
            $task->profitSharingErrHandler(['发起退款失败 ' . ($res->return_msg ?? '')]);
            return;
        }
        if (isset($res->err_code)) {
            $this->reNumber += 1;
            Db::rollback();
            $task->profitSharingErrHandler(['发起退款失败 错误码:' . ($res->err_code_des ?? '')]);
            Log::error("退款失败 正在进行重新尝试" . ($res->err_code_des ?? ''));
            if ($this->reNumber >= 5) {
                return;
            }
            sleep(5);
            $this->runner($task);
            return;
        }
        /** @var RefundTaskDao $refundTaskDao */
        $refundTaskDao = app()->make(RefundTaskDao::class);
        $refundTaskDao->upRefundTime($task->getAttr('refund_task_id'));

        Queue::later(60, RefundCheckJob::class, [
            'refund_task_id' => $task->getAttr('refund_task_id')
        ]);
    }

    /**
     * 存储任务的新参数
     *
     * @param RefundTask $task
     * @param array $param
     * @return void
     */
    private function saveTaskParam(RefundTask $task, array $param)
    {
        $newTask = clone $task;
        $newTask->setAttr('param', json_encode($param, JSON_UNESCAPED_UNICODE));
        $newTask->save();
    }

    /**
     * @param string $code
     * @param string $mchId
     * @return string
     */
    private function errorCode(string $code, string $mchId): string
    {
        $err =  [
            'ACCOUNT_ABNORMAL' => '原分账接收方账户异常',
            'TIME_OUT_CLOSED' => '超时关单',
            'PAYER_ACCOUNT_ABNORMAL' => '原分账分出方账户异常',
            'INVALID_REQUEST' => '描述参数设置失败'
        ][$code] ?? $code;

        return "错误原因: {$err} 微信商户号: {$mchId}";
    }
}
