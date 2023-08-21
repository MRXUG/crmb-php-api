<?php

namespace crmeb\listens;

use app\common\model\delivery\DeliveryProfitSharingLogs;
use app\common\model\delivery\DeliveryProfitSharingStatus;
use app\common\model\store\order\OrderFlow;
use app\common\model\store\order\StoreOrder;
use app\common\model\store\OrderFinishTask;
use app\common\repositories\delivery\DeliveryProfitSharingLogsRepository;
use app\common\repositories\delivery\DeliveryProfitSharingStatusPartRepository;
use app\common\repositories\delivery\DeliveryProfitSharingStatusRepository;
use app\common\repositories\store\order\OrderFlowRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\system\merchant\MerchantGoodsPaymentRepository;
use crmeb\interfaces\ListenerInterface;
use crmeb\services\TimerService;
use crmeb\services\WechatService;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;

class FinishOrderListen extends TimerService implements ListenerInterface
{
    protected string $name = "15天后分账回退，收取服务费：" . __CLASS__;

    public function handle($event): void
    {
        $this->tick(1000 * 60 *5, function () {
            request()->clearCache();

            $app   = app()->make(StoreOrderRepository::class);
            //date('Y-m-d H:i:s', time() - 86400 * 15)
            $task = OrderFinishTask::getDB()->where([['finish_at','<',time()-60*5],['status','=',0]])->select()->toArray();
            $orderIds = array_column($task,'order_id');
            if(empty($orderIds)){
                return;
            }
            $orders = $app->getNeedFinishOrdersByIds($orderIds,'order_id,system_commission,platform_source,mer_id,appid,order_sn');
            if (empty($orders)) {
                return;
            }
            $orderByKeys = array_column($orders, null, 'order_id');
            //获取分账成功的订单
            $data        = app()
                ->make(DeliveryProfitSharingStatusRepository::class)
                ->whereIn('order_id', array_keys($orderByKeys))
                ->where('profit_sharing_status', DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_SUCCESS)
                ->select()
                ->toArray();
            
            foreach($data as $item){
                \think\facade\Log::info('[15天回退订单],order_id:' . $item['order_id']);
                $this->profitSharingReturn($item, $orderByKeys[$item['order_id']]);
            }
        });
    }

    /**
     * 分账回退
     *
     * @param $data
     * @param $log
     * @param $order
     *
     * @throws \Exception
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/9 14:29
     */
    protected function profitSharingReturn($data, $order)
    {
        // 获取分佣比例
        $returnAmount = app()
            ->make(StoreOrderRepository::class)
            ->calcProfitSharingAmountByPlatformSource($data, $order);
        $update = [
            'profit_sharing_status' => DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_ING,
            'return_amount'         => $returnAmount,
            'profit_sharing_error'  => '',
        ];

        $params = $res = [];
        switch ($order['platform_source']){
            case StoreOrder::PLATFORM_SOURCE_NATURE:
                //自然流量分账不回退
                $update = [
                    'profit_sharing_status' => DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_SUCCESS_PART,
                    'return_amount'         => 0,
                    'profit_sharing_error'  => '自然流量分账不回退',
                ];
                break;
            case StoreOrder::PLATFORM_SOURCE_BACK_FLOW:
                //部分分账 -- 回流流量
                $update = [
                    'profit_sharing_status' => DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_SUCCESS_PART,
                    'return_amount'         => $returnAmount + $data['return_amount'], //已经退款的总额
                    'profit_sharing_error'  => '',
                ];
                try {
                    if($returnAmount <= 0){
                        throw new \Exception('回流流量分账回退失败：回退金额小于0:'.$returnAmount);
                    }
                    if($returnAmount + $data['return_amount'] > $data['amount']){
                        throw new \Exception('回流流量分账回退失败：回退总金额大于分账金额:'.$returnAmount);
                    }
                    // 获取商户配置
                    $make   = WechatService::getMerPayObj($order['mer_id'], $order['appid']);
                    // 自定义32位
                    $out_return_no = $data['order_sn'].'r'.time();
                    $params = [
                        'out_order_no'  => $data['order_sn'],
                        'out_return_no' => $out_return_no,
                        'return_mchid'  => (string) $data['mch_id'],
                        'amount'        => (int) $returnAmount,
                        'description'   => '分账部分回退',
                    ];
                    $res = $make->profitSharing()->profitSharingReturn($params);
                    if(isset($res['result']) && $res['result']=='SUCCESS'){
                        $update['profit_sharing_status'] = DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_SUCCESS_PART;
//                        if($returnAmount + $data['return_amount'] == $data['amount']){
//                            //已经全部回退 目前应该不会触达
//                            $update['profit_sharing_status'] = DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_SUCCESS;
//                        }
                    }
                    if(isset($res['result']) && $res['result']=='FAILED'){
                        throw new \Exception('分账回退失败：' . json_encode($res, JSON_UNESCAPED_UNICODE));
                    }
                    //成功回退 记录数据
                    app()->make(DeliveryProfitSharingStatusPartRepository::class)->create([
                        'order_id'                          => $data['order_id'],
                        'delivery_profit_sharing_status_id' => $data['id'],
                        'out_return_no'                     => $out_return_no,
                        'part_return_amount'                => $returnAmount,
                        'result'                            => $res['result'] ?? 'ERROR_404',
                    ]);
                } catch (ValidateException $exception) {
                    \think\facade\Log::error('order_id:'.$data['order_id'] . ',收货15天后押金回退失败' . $exception->getMessage());
                    $update['profit_sharing_status'] = DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_FAIL;
                    $update['profit_sharing_error']  = $exception->getMessage();
                }
                break;
            default:
                try {
                    if($returnAmount <= 0){
                        throw new \Exception('分账回退失败：回退金额小于0:'.$returnAmount);
                    }
                    if($returnAmount + $data['return_amount'] > $data['amount']){
                        throw new \Exception('分账回退失败：回退总金额大于分账金额:'.$returnAmount);
                    }
                    // 获取商户配置
                    $make   = WechatService::getMerPayObj($order['mer_id'], $order['appid']);
                    $params = [
                        'out_order_no'  => $data['order_sn'],
                        'out_return_no' => $data['order_sn'] ?? $data['order_sn'] . substr(0, 4, time()),
                        'return_mchid'  => (string) $data['mch_id'],
                        'amount'        => (int) $returnAmount,
                        'description'   => '分账回退',
                    ];
                    $res = $make->profitSharing()->profitSharingReturn($params);
                    if(isset($res['result']) && $res['result']=='SUCCESS'){
                        $update['profit_sharing_status'] = DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_SUCCESS;
                    }
                    if(isset($res['result']) && $res['result']=='FAILED'){
                        throw new \Exception('分账回退失败：' . json_encode($res, JSON_UNESCAPED_UNICODE));
                    }
                } catch (ValidateException $exception) {
                    \think\facade\Log::error('order_id:'.$data['order_id'] . ',收货15天后押金回退失败' . $exception->getMessage());
                    $update['profit_sharing_status'] = DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_FAIL;
                    $update['profit_sharing_error']  = $exception->getMessage();
                }
                break;
        }
        //更新分账操作状态
        app()->make(DeliveryProfitSharingStatusRepository::class)->updateByWhere(['order_id' => $data['order_id']], $update);
        //记录回退日志
        app()->make(DeliveryProfitSharingLogsRepository::class)->create([
            'type'           => DeliveryProfitSharingLogs::RETURN_ORDERS_TYPE,
            'out_order_no'   => $data['order_sn'],
            'request'        => json_encode($params, JSON_UNESCAPED_UNICODE),
            'response'       => json_encode($res, JSON_UNESCAPED_UNICODE),
            'order_id'       => $data['order_id'],
            'transaction_id' => $data['transaction_id'],
        ]);
        OrderFinishTask::getDB()->where('order_id','=',$data['order_id'])->update([
            'status'=>1,
            'err_msg'=>$update['profit_sharing_error']
        ]);
        if($update['profit_sharing_status']== DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_SUCCESS){
            if($order['platform_source'] != StoreOrder::PLATFORM_SOURCE_NATURE){
                app()->make(OrderFlowRepository::class)->create([
                    'order_sn'    => $order['order_sn'],
                    'create_time' => date('Y-m-d H:i:s'),
                    'type'        => OrderFlow::FLOW_TYPE_IN,
                    'amount'      => '+' . $update['return_amount'],
                    'remark'      => $this->getTitleByPlatformSource($order),
                    'mer_id'      => $data['mer_id'],
                    'mch_id'      => $data['mch_id'],
                    'is_del'      => OrderFlow::DELETE_FALSE,
                ]);
            }
            app()->make(MerchantGoodsPaymentRepository::class)->updateWhenReceivePlus15Days($order['order_id'], [
                'deposit_money' => 0,
            ]);
        }
    }

    /**
     * 测试方法
     * @param int $order_id
     * @throws \Exception
     */
    public function test($order_id = 1116){
        $app   = app()->make(StoreOrderRepository::class);
        $orders = $app->getNeedFinishOrdersByIds([$order_id],'order_id,system_commission,platform_source,mer_id,appid,order_sn');
        $data = app()
            ->make(DeliveryProfitSharingStatusRepository::class)
            ->whereIn('order_id', $order_id)
            ->find()
            ->toArray();
        $order = $orders[0];
        $res = $this->profitSharingReturn($data, $order);
        echo json_encode($res);
        return $res;
    }

    /**
     * 获取流水标题
     *
     * @param $order
     *
     * @return string
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/14 15:09
     */
    protected function getTitleByPlatformSource($order)
    {
        $title = '';
        switch ($order['platform_source']) {
            case StoreOrder::PLATFORM_SOURCE_BACK_FLOW:
                $title = OrderFlow::BACK_FLOW_PROFIT_SHARING_REFUND_CN;
                break;
            case StoreOrder::PLATFORM_SOURCE_NATURE:
                $title = OrderFlow::NATURAL_FLOW_PROFIT_SHARING_CN;
                break;
            case StoreOrder::PLATFORM_SOURCE_AD:
                $title = OrderFlow::REFUND_PROFIT_SHARING_CN;
                break;

        }

        return $title;
    }

    /**
     * 处理分账回退结果
     *
     * @param $res
     * @param $update
     *
     * @return bool
     * @throws \Exception
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/14 14:41
     */
    protected function handleRes($res, &$update)
    {
        if (empty($res)) {
            return true;
        }

        if ($res['result'] == 'PROCESSING') {
            return true;
        } elseif ($res['result'] == 'SUCCESS') {
            $update['profit_sharing_status'] = DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_SUCCESS;

        } elseif ($res['result'] == 'FAILED') {
            throw new \Exception('分账回退失败：' . json_encode($res, JSON_UNESCAPED_UNICODE));
        }
    }
}
