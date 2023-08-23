<?php

namespace crmeb\listens;

use app\common\dao\store\order\StoreRefundOrderDao;
use app\common\dao\store\RefundTaskDao;
use app\common\model\delivery\DeliveryProfitSharingLogs;
use app\common\model\delivery\DeliveryProfitSharingStatus;
use app\common\model\delivery\DeliveryProfitSharingStatusPart;
use app\common\model\store\order\StoreOrder;
use app\common\model\store\RefundTask;
use app\common\repositories\delivery\DeliveryProfitSharingStatusPartRepository;
use app\common\repositories\delivery\DeliveryProfitSharingStatusRepository;
use app\common\repositories\delivery\DeliveryProfitSharingLogsRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use crmeb\interfaces\ListenerInterface;
use crmeb\jobs\RefundCheckJob;
use crmeb\services\MiniProgramService;
use crmeb\services\TimerService;
use crmeb\services\WechatService;
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
            $task                = RefundTask::getDB()->where('status', 0)->select();
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
                        $res = $this->checkRefundResult($item, $info);
                        # 回退结果
                        $return_status = DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_ING;
                        switch ($res['result']) {
                            case 'SUCCESS':
                                $return_status = DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_SUCCESS;
                                break;
                            case 'FAILED':
                                $return_status = DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_FAIL;
                                # 处理错误信息
                                $item->profitSharingErrHandler([$this->errorCode($res['fail_reason'], $item['app_id'])]);
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
                    $item->setAttr('status', -1);
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

    public function checkRefundResult(RefundTask $item, $info){
        $orderId = $item['order_id'];
        $orderSn = $item->getAttr('order_sn');
        $merId = $item->getAttr('mer_id');
        // 获取商户配置
        $make   = WechatService::getMerPayObj($merId, $item->getAttr('app_id'));
        // 查询回退结果 原始逻辑
        if($info['profit_sharing_status'] != DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_SUCCESS_PART){
            // 分账作为押金属性，会由售后押款统一回退 此处不需要重复回退，直接查询即可  ProfitSharing::createRefundTask
            return $make->profitSharing()
                ->profitSharingReturnResult($orderSn, $orderSn);
        }

        /** @var DeliveryProfitSharingStatusPart $profitSharingPartRepos */
        $profitSharingPartRepos = app()->make(DeliveryProfitSharingStatusPartRepository::class);
        switch ($info['platform_source']){
            case StoreOrder::PLATFORM_SOURCE_NATURE:
                // 自然流量分账未回退，此处需要回退
                // 使用 DeliveryProfitSharingStatusPart 记录分账信息，避免重复提交分账
                if($part = $profitSharingPartRepos->where('delivery_profit_sharing_status_id', $info['id'])->find()){
                    //查询回退结果
                    if($part['result'] == 'SUCCESS'){
                        return ['result' => 'SUCCESS'];
                    }
                    return $make->profitSharing()
                        ->profitSharingReturnResult($part['out_return_no'], $orderSn);
                }else{
                    //分账回退
                    // 自定义32位
                    $out_return_no = $orderSn.'r'.time();
                    $params = [
                        'out_order_no'  => $orderSn,
                        'out_return_no' => $out_return_no,
                        'return_mchid'  => (string) $info['mch_id'],
                        'amount'        => (int) $info['amount'],
                        'description'   => '退款分账回退',
                    ];
                    $res = $make->profitSharing()->profitSharingReturn($params);
                    if(isset($res['result']) && $res['result']=='FAILED'){
                        throw new \Exception('自然流量分账回退失败：' . json_encode($res, JSON_UNESCAPED_UNICODE));
                    }
                    //成功回退 记录数据
                    app()->make(DeliveryProfitSharingStatusPartRepository::class)->create([
                        'order_id'                          => $orderId,
                        'delivery_profit_sharing_status_id' => $info['id'],
                        'out_return_no'                     => $out_return_no,
                        'part_return_amount'                => $info['amount'],
                        'result'                            => $res['result'] ?? 'ERROR_404',
                    ]);
                    return $res;
                }
                break;
            case StoreOrder::PLATFORM_SOURCE_BACK_FLOW:
                // 回流 部分分账回退， 需要退回剩下部分
                $leftAmount = $info['amount'] - $info['return_amount'];
                if($leftAmount > 0){
                    $leftOrderOurReturnNo = $orderSn.'r1final'; //最后部分
                    if($part = $profitSharingPartRepos->where([
                        'delivery_profit_sharing_status_id' => $info['id'],
                        'out_return_no' => $leftOrderOurReturnNo
                    ])->find()){
                        if($part['result'] == 'SUCCESS'){
                            return ['result' => 'SUCCESS'];
                        }
                        //查询回退结果
                        return $make->profitSharing()
                            ->profitSharingReturnResult($part['out_return_no'], $orderSn);
                    }else{
                        //分账回退
                        // 自定义32位
                        $params = [
                            'out_order_no'  => $orderSn,
                            'out_return_no' => $leftOrderOurReturnNo,
                            'return_mchid'  => (string) $info['mch_id'],
                            'amount'        => (int) $leftAmount,
                            'description'   => '退款分账回退',
                        ];
                        $res = $make->profitSharing()->profitSharingReturn($params);
                        if(isset($res['result']) && $res['result']=='FAILED'){
                            throw new \Exception('自然流量分账回退失败：' . json_encode($res, JSON_UNESCAPED_UNICODE));
                        }
                        //成功回退 记录数据
                        app()->make(DeliveryProfitSharingStatusPartRepository::class)->create([
                            'order_id'                          => $orderId,
                            'delivery_profit_sharing_status_id' => $info['id'],
                            'out_return_no'                     => $leftOrderOurReturnNo,
                            'part_return_amount'                => $info['amount'],
                            'result'                            => $res['result'] ?? 'ERROR_404',
                        ]);
                        return $res;
                    }
                }else{
                    $returnOrderNo = $profitSharingPartRepos->where([
                        'delivery_profit_sharing_status_id' => $info['id'],
                    ])->find();

                    //查询回退结果
                    return $returnOrderNo ? $make->profitSharing()
                        ->profitSharingReturnResult($returnOrderNo['out_return_no'], $orderSn) :
                        ['result' => 'FAILED', 'fail_reason' => '回流分账记录DeliveryProfitSharingStatusPart不存在'];
                }
                break;
            default:
                //查询回退结果 原始逻辑
                return $make->profitSharing()
                    ->profitSharingReturnResult($orderSn, $orderSn);

        }
    }

    /**
     * 测试方法
     * @param int $refundTaskId
     * @return array|mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function test($refundTaskId = 7395){
        $item = RefundTask::getDB()->where("refund_task_id", $refundTaskId)->find();
        $profitSharingStatus = app()->make(DeliveryProfitSharingStatusRepository::class);
        $info = $profitSharingStatus->getProfitSharingStatus($item->getAttr('order_id'));
//        var_dump($info['platform_source']);exit;
        $info['platform_source'] = 1;
        return $this->checkRefundResult($item, $info);
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
