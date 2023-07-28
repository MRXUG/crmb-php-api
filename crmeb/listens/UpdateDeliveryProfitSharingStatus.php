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
use think\db\concern\Transaction;
use think\db\exception\DbException;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;

class UpdateDeliveryProfitSharingStatus extends TimerService implements ListenerInterface
{
    protected string $name = "更新发货分佣结果：" . __CLASS__;

    public function handle($event): void
    {
        $this->tick(1000 * 60 * 1, function () {
            $cache = Cache::store('redis')->handler()->get(__CLASS__);
            \think\facade\Log::info($this->name.'_start：'.date('Y-m-d H:i:s').'lock:'.$cache);
            if ($cache) {
                return true;
            }
            
            Cache::store('redis')->handler()->set(__CLASS__, date('Y-m-d H:i:s'));
            $limit = 50;
            $maxOrderId = 0;
            $app = app()->make(DeliveryProfitSharingStatusRepository::class);
            
            $fields = [
                'order_id',
                'order_sn',
                'mer_id',
                'appid',
                'transaction_id',
                'pay_price',
                'platform_source',
            ];
            while (true) {
                // 查询已发货分佣中的订单
                $where = [['profit_sharing_status', '=', DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_ING]];
                if ($maxOrderId) {
                    array_push($where, ['order_id', '>', $maxOrderId]);
                }

                
                $data = $app->getDeliveryProfitSharingOrder($limit, $where);
                if (empty($data)) {
                    break;
                }

                $dataByKeys = array_column($data, null, 'order_id');
                // 查询订单
                $orders = app()
                    ->make(StoreOrderRepository::class)
                    ->getStoreOrderByWhereIn('order_id', array_keys($dataByKeys), $fields);
                // 查询请求分账日志
                $orderByKeys = array_column($orders, null, 'order_id');
                $logs = app()
                    ->make(DeliveryProfitSharingLogsRepository::class)
                    ->getProfitSharingOrder('order_id', array_keys($orderByKeys));
                $logByKeys = array_column($logs, null, 'order_id');
                foreach ($orders as $order) {
                    // 查询分佣结果
                    $this->profitSharingResult($order, $logByKeys[$order['order_id']], $dataByKeys[$order['order_id']]);
                    $maxOrderId = $order['order_id'];
                }
            }
            \think\facade\Log::info($this->name.'_end：'.date('Y-m-d H:i:s'));
            Cache::store('redis')->handler()->del(__CLASS__);
        });
    }


    /**
     * 查询分佣结果
     *
     * @param $order
     * @param $log
     * @param $data
     *
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/6 16:24
     */
    protected function profitSharingResult($order, $log, $data)
    {
        $update = [
            'profit_sharing_error' => '',
            'profit_sharing_status' => DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_ING
        ];

        $params = [
            'transaction_id' => $order['transaction_id'],
            'out_order_no' => $log['out_order_no']
        ];
        $res = [];
        try {
            $make = WechatService::getMerPayObj($order['mer_id'], $order['appid']);
            $res = $make->profitSharing()->profitSharingResult($log['out_order_no'], $order['transaction_id']);
            // 处理分账结果
            $this->handleResult($res, $update);
        } catch (ValidateException $exception) {
            $update = [
                'profit_sharing_error' => $exception->getMessage(),
                'profit_sharing_status' => DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_FAIL
            ];
        }

        try {
            Db::transaction(function () use ($order, $update, $params, $res, $data) {
                // 记录本次分佣结果
                !empty($update) && app()->make(DeliveryProfitSharingStatusRepository::class)->updateByWhere([
                    'order_id' => $order['order_id']
                ], $update);

                app()->make(DeliveryProfitSharingLogsRepository::class)->create([
                    'type' => DeliveryProfitSharingLogs::PROFIT_SHARING_TYPE,
                    'out_order_no' => $params['out_order_no'],
                    'request' => json_encode($params, JSON_UNESCAPED_UNICODE),
                    'response' => json_encode($res, JSON_UNESCAPED_UNICODE),
                    'order_id' => $order['order_id'],
                    'transaction_id' => $order['transaction_id']
                ]);

                // 分账成功
                if ($update['profit_sharing_status'] == DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_SUCCESS) {
                    // 给分佣商户分账成功
                    app()->make(MerchantGoodsPaymentRepository::class)->updateWhenDeliveryPlus1Day($order['order_id'], [
                        'mchId' => $data['mch_id'],
                        'deposit_money' => bcmul($data['total'], $data['deposit_rate'])
                    ]);

                    $where = [
                        'amount' => $data['amount'],
                        'type' => OrderFlow::FLOW_TYPE_PROFIT_SHARING,
                        'mer_id' => $order['mer_id'],
                        'mch_id' => $data['mch_id'],
                        'order_sn' => $order['order_sn'],
                        'remark' => $this->getTitleByPlatformSource($order['platform_source']),
                        'is_del' => OrderFlow::DELETE_FALSE,
                        'profit_sharing_id' => $res['order_id']
                    ];
                    // 记录流水
                    app()->make(OrderFlowRepository::class)->createOrUpdate($where, array_merge([
                        'create_time' => date('Y-m-d H:i:s'),
                    ], $where));
                }
            });
        } catch (DbException $e) {
            Log::error($order['order_id'] . '记录查询发货分佣结果失败：' . $e->getMessage(), $update);
        }
    }

    /**
     * 获取标题
     *
     * @param $platformSource
     *
     * @return string
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/14 21:06
     */
    protected function getTitleByPlatformSource($platformSource)
    {
        $title = '';
        switch ($platformSource) {
            case StoreOrder::PLATFORM_SOURCE_NATURE:
                $title = OrderFlow::NATURAL_FLOW_PROFIT_SHARING_CN;
                break;
            case StoreOrder::PLATFORM_SOURCE_BACK_FLOW;
            case StoreOrder::PLATFORM_SOURCE_AD:
                $title = OrderFlow::PROFIT_SHARING_CN;
                break;
        }
        
        return $title;
    }

    /**
     * 处理查询分账结果
     *
     * @param $res
     * @param $update
     *
     * @return bool
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/9 16:47
     */
    protected function handleResult($res, &$update)
    {
        if (empty($res)) {
            return true;
        }
        
        $reProfitSharing = false;
        foreach ($res['receivers'] as $receiver) {
            // 分账接收方分账异常-重新分给别的商户
            if ($receiver['result'] == "CLOSED") {
                $reProfitSharing = true;
                break;
            }

            if ($receiver['result'] == 'SUCCESS') {
                $update['profit_sharing_status'] = DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_SUCCESS;
                break;
            }
        }

        if (!$reProfitSharing) {
            return true;
        }

        throw new ValidateException('查询分佣结果：失败:' . json_encode($res, JSON_UNESCAPED_UNICODE));
    }


    /**
     * 获取商户支付对象
     *
     * @param $order
     *
     * @return WechatService
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/3 14:58
     */
    protected function getMerPayObj($order)
    {
        // 获取商户配置
        $merPayConfig = WechatService::getV3PayConfig($order['mer_id'], $order['appid']);
        return new WechatService($merPayConfig);
    }
}