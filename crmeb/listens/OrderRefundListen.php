<?php

namespace crmeb\listens;

use app\common\dao\store\order\StoreRefundOrderDao;
use app\common\dao\store\RefundTaskDao;
use app\common\model\delivery\DeliveryProfitSharingLogs;
use app\common\model\delivery\DeliveryProfitSharingStatus;
use app\common\model\store\RefundTask;
use app\common\repositories\delivery\DeliveryProfitSharingStatusRepository;
use app\common\repositories\delivery\DeliveryProfitSharingLogsRepository;
use crmeb\interfaces\ListenerInterface;
use crmeb\jobs\RefundCheckJob;
use crmeb\services\MiniProgramService;
use crmeb\services\TimerService;
use crmeb\services\WechatService;
use crmeb\utils\wechat\ProfitSharing;
use Exception;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;
use think\facade\Queue;
use Throwable;
use ValueError;

class OrderRefundListen extends TimerService implements ListenerInterface
{

    protected string $name = '订单退款处理-' . __CLASS__;

    public function handle($event): void
    {
        # 每半分钟检测一次
        $this->tick(1000*60*5, function () {
            request()->clearCache();
            # 查询获取任务信息·
            //** @var RefundTask[] $task */
            $task                = RefundTask::getDB()->where('order_id', 1118)->select();
            //$task                = RefundTask::getDB()->where('status', 0)->select();
            $profitSharingStatus = app()->make(DeliveryProfitSharingStatusRepository::class);
            foreach ($task as $item) {
                try {
                    Log::info("开始处理 {$this->name} " . date("Y-m-d H:i:s") . $item->getAttr('order_sn'));
                    //获取是否进行过分账
                    $info = $profitSharingStatus->getProfitSharingStatus($item->getAttr('order_id'));

                    if(isset($info['profit_sharing_status'])&&$info['profit_sharing_status']==DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_DEFAULT){
                        app()->make(DeliveryProfitSharingStatusRepository::class)->updateByWhere([
                            'order_id' => $item['order_id'],
                        ], ['profit_sharing_status' => DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_FNIAL]);
                    }
                    if (!empty($info)&&$info['profit_sharing_status']!=DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_DEFAULT) {
                        //查询分账处理状态
                        $res = WechatService::getMerPayObj($item->getAttr('mer_id'), $item->getAttr('app_id'))
                            ->profitSharing()
                            ->profitSharingReturnResult($item->getAttr('order_sn'), $item->getAttr('order_sn')); //TODO 确认是否都是用的订单号
                        # 回退结果
                        $return_status = DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_ING;
                        switch ($res['result']) {
                            case 'SUCCESS':
                                $return_status = DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_SUCCESS;
                                break;
                            case 'FAILED':
                                $return_status = DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_FAIL;
                                # 处理错误信息
                                $item->profitSharingErrHandler([$this->errorCode($res['fail_reason'], $item['returnMchId'])]);
                                break;
                            default:
                                $return_status = DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_ING;
                                break;
                        }
                        //分账回退成功 进行退款操作
                        if ($return_status == DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_SUCCESS) {
                            $this->refundToUser($item);
                            $item->setAttr('status', 1);
                            $item->save();
                        }
                    } else {
                        $this->refundToUser($item);
                        $item->setAttr('status', 1);
                        $item->save();
                    }
                } catch (Exception | ValueError | Throwable $e) {
                    Log::error("运行出错 {$this->name} order_id" . $item->getAttr('order_id') . date("Y-m-d H:i:s") . $e->getMessage()."line:".$e->getLine());
                    $item->setAttr('err_msg', "运行出错 {$this->name} order_id" . $item->getAttr('order_id') . date("Y-m-d H:i:s") . $e->getMessage());
                    $item->save();
                }
            }
        });
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
        /** @var StoreRefundOrderDao $refundOrderResp */
        $refundOrderResp = app()->make(StoreRefundOrderDao::class);
        $refundOrder     = $refundOrderResp->getWhere(['refund_order_id' => $task->getAttr('refund_order_id')], "*", ['order']);
        # 发起退款
        $orderNo     = $refundOrder->order->pay_order_sn;
        $refundId    = $refundOrder->refund_order_sn;
        $payPrice    = $refundOrder->order->groupOrder->pay_price;
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
            $task->profitSharingErrHandler(['发起退款失败 错误码:' . ($res->err_code_des ?? '')]);
            Log::error("退款失败 正在进行重新尝试" . ($res->err_code_des ?? ''));
            return;
        }
        /** @var RefundTaskDao $refundTaskDao */
        $refundTaskDao = app()->make(RefundTaskDao::class);
        $refundTaskDao->upRefundTime($task->getAttr('refund_task_id'));

        Queue::later(60, RefundCheckJob::class, [
            'refund_task_id' => $task->getAttr('refund_task_id'),
        ]);
    }

    /**
     * @param string $code
     * @param string $mchId
     * @return string
     */
    private function errorCode(string $code, string $mchId): string
    {
        $err = [
            'ACCOUNT_ABNORMAL'       => '原分账接收方账户异常',
            'TIME_OUT_CLOSED'        => '超时关单',
            'PAYER_ACCOUNT_ABNORMAL' => '原分账分出方账户异常',
            'INVALID_REQUEST'        => '描述参数设置失败',
        ][$code] ?? $code;

        return "错误原因: {$err} 微信商户号: {$mchId}";
    }

        /**
     * 处理返回结果
     *
     * @param $res
     * @param $update
     *
     * @return bool
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/13 20:35
     */
    protected function handleStatus($res, &$update)
    {
        if (empty($res)) {
            return true;
        }

        $status = DeliveryProfitSharingStatus::PROFIT_SHARING_UNFREEZE_ING;
        foreach ($res['receivers'] as $receiver) {
            if ($receiver['result'] == 'CLOSED') {
                $status = DeliveryProfitSharingStatus::PROFIT_SHARING_UNFREEZE_FAIL;
                break;
            }
        }

        $update['unfreeze_status'] = $status;
    }
}
