<?php


namespace crmeb\listens;


use app\common\model\delivery\DeliveryProfitSharingLogs;
use app\common\model\delivery\DeliveryProfitSharingStatus;
use app\common\model\store\order\OrderFlow;
use app\common\model\store\order\StoreOrder;
use app\common\repositories\delivery\DeliveryProfitSharingLogsRepository;
use app\common\repositories\delivery\DeliveryProfitSharingStatusRepository;
use app\common\repositories\store\order\OrderFlowRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\system\merchant\MerchantGoodsPaymentRepository;
use crmeb\interfaces\ListenerInterface;
use crmeb\services\TimerService;
use crmeb\services\WechatService;
use think\exception\ValidateException;
use think\facade\Db;

class FinishOrderListen extends TimerService implements ListenerInterface
{
    protected string $name = "15天后分账回退，收取服务费：" . __CLASS__;

    public function handle($event): void
    {
        $this->tick(1000 * 60 * 5, function () {
            \think\facade\Log::info($this->name.'_start：'.date('Y-m-d H:i:s'));
            $maxOrderId = 0;
            /**
             * @var StoreOrderRepository $app
             */
            $app = app()->make(StoreOrderRepository::class);
            $where = [
                [
                    'verify_time',
                    '<',
                    date('Y-m-d H:i:s', time() - 86400 * 15)
//                    date('Y-m-d H:i:s', time() - 600)
                ]
            ];

            $field = 'order_id,system_commission,platform_source,mer_id,appid,order_sn';
            $limit = 50;
            while (true) {
                if ($maxOrderId) {
                    array_push($where, ['order_id', '>', $maxOrderId]);
                }

                $orders = $app->getNeedFinishOrders($where, $limit, $field);
                if (empty($orders)) {
                    break;
                }

                /**
                 * 获取发货分账记录
                 * @var DeliveryProfitSharingStatusRepository $app
                 */

                $orderByKeys = array_column($orders, null, 'order_id');
                $data = app()
                    ->make(DeliveryProfitSharingStatusRepository::class)
                    ->whereIn('order_id', array_keys($orderByKeys))
                    ->where('profit_sharing_status', DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_SUCCESS)
                    ->select()
                    ->toArray();

                if (empty($data)) {
                    break;
                }

                // 查询请求分账的记录
                $logs = app()
                    ->make(DeliveryProfitSharingLogsRepository::class)
                    ->getProfitSharingOrder('order_id', array_column($data, 'order_id'));
                $logByKeys = array_column($logs, null, 'order_id');
                foreach ($data as $value) {
                    // 押金回退
                    $this->profitSharingReturn($value, $logByKeys[$value['order_id']], $orderByKeys[$value['order_id']]);
                    // sleep(1);
                    $maxOrderId = $value['order_id'];
                }
            }
             \think\facade\Log::info($this->name.'_end：'.date('Y-m-d H:i:s'));
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
    protected function profitSharingReturn($data, $log, $order)
    {
        // 获取分佣比例
        $returnAmount = app()
            ->make(StoreOrderRepository::class)
            ->calcProfitSharingAmountByPlatformSource($data, $order);
        $update = [
            'profit_sharing_status' => DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_ING,
            'return_amount' => $returnAmount,
            'profit_sharing_error' => ''
        ];

        $params = $res = [];
        try {
            // 获取商户配置
            $make = WechatService::getMerPayObj($order['mer_id'], $order['appid']);
            $params = [
                'out_order_no' => $log['out_order_no'],
                'out_return_no' => $log['out_return_no'] ?? $log['out_order_no'] . substr(0, 4, time()),
                'return_mchid' => (string)$data['mch_id'],
                'amount' => (int)$returnAmount,
                'description' => '分账回退',
            ];
            $res = $make->profitSharing()->profitSharingReturn($params);
            $this->handleRes($res, $update);
        } catch (ValidateException $exception) {
            \think\facade\Log::error($log['order_id'] . '收货15天后押金回退失败' . $exception->getMessage());
            $update['profit_sharing_status'] = DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_FAIL;
            $update['profit_sharing_error'] = $exception->getMessage();
        }

        Db::transaction(function () use ($log, $update, $params, $res, $data, $order) {
            // 更新发货分佣回调状态
            app()
                ->make(DeliveryProfitSharingStatusRepository::class)
                ->updateByWhere(['order_id' => $log['order_id']], $update);

            app()->make(DeliveryProfitSharingLogsRepository::class)->create([
                'type' => DeliveryProfitSharingLogs::RETURN_ORDERS_TYPE,
                'out_order_no' => $log['out_order_no'],
                'request' => json_encode($params, JSON_UNESCAPED_UNICODE),
                'response' => json_encode($res, JSON_UNESCAPED_UNICODE),
                'order_id' => $log['order_id'],
                'transaction_id' => $log['transaction_id']
            ]);

            if ($update['profit_sharing_status'] == DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_SUCCESS) {
                // 记录流水
                if ($order['platform_source'] != StoreOrder::PLATFORM_SOURCE_NATURE) {
                    app()->make(OrderFlowRepository::class)->create([
                        'order_sn' => $order['order_sn'],
                        'create_time' => date('Y-m-d H:i:s'),
                        'type' => OrderFlow::FLOW_TYPE_IN,
                        'amount' => '+'.$update['return_amount'],
                        'remark' => $this->getTitleByPlatformSource($order),
                        'mer_id' => $data['mer_id'],
                        'mch_id' => $data['mch_id'],
                        'is_del' => OrderFlow::DELETE_FALSE
                    ]);
                }

                app()->make(MerchantGoodsPaymentRepository::class)->updateWhenReceivePlus15Days($order['order_id'], [
                    'deposit_money' => 0
                ]);
            }
        });
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
