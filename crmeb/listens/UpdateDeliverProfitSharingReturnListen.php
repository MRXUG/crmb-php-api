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
use crmeb\interfaces\ListenerInterface;
use crmeb\services\TimerService;
use crmeb\services\WechatService;
use think\facade\Db;

class UpdateDeliverProfitSharingReturnListen extends TimerService implements ListenerInterface
{
    protected string $name = "更新分佣回退状态：" . __CLASS__;

    public function handle($event): void
    {
        $this->tick(1000 * 60 * 1, function () {
            \think\facade\Log::info($this->name.'_start：'.date('Y-m-d H:i:s'));
            $limit = 50;
            $maxOrderId = 0;
            while (true) {
                $where = [
                    [
                        'profit_sharing_status',
                        '=',
                        DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_ING
                    ]
                ];

                if ($maxOrderId) {
                    array_push($where, ['order_id', '>', $maxOrderId]);
                }

                $data = app()
                    ->make(DeliveryProfitSharingStatusRepository::class)
                    ->profitSharingReturnIng($limit, $where);
                if (empty($data)) {
                    break;
                }

                $dataByKeys = array_column($data, null, 'order_id');
                // 查询分账回退日志
                $logs = app()
                    ->make(DeliveryProfitSharingLogsRepository::class)
                    ->getProfitSharingReturnLog('order_id', array_keys($dataByKeys));
                foreach ($logs as $log) {
                    // 查询分账回退结果
                    $this->getProfitSharingReturnResult($log, $dataByKeys[$log['order_id']]);
                    $maxOrderId = $log['order_id'];
                    // sleep(1);
                }
            }
             \think\facade\Log::info($this->name.'_end：'.date('Y-m-d H:i:s'));
        });
    }

    /**
     * 查询分帐回退结果
     *
     * @param $log
     * @param $data
     *
     * @return bool
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/11 18:30
     */
    protected function getProfitSharingReturnResult($log, $data)
    {
        $params = [
            'out_return_no' => $log['out_return_no'],
            'out_order_no' => $log['out_order_no']
        ];
        $res = $update = [];
        try {
            $make = WechatService::getMerPayObj($data['mer_id'], $data['app_id']);
            $res = $make->profitSharing()->profitSharingReturnResult($log['out_return_no'], $log['out_order_no']);
            // 处理结果
            if (empty($res)) {
                return true;
            }

            if ($res['result'] == 'PROCESSING') {
                return true;
            } elseif ($res['result'] == 'SUCCESS') {
                $update = [
                    'profit_sharing_status' => DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_SUCCESS,
                    'profit_sharing_error' => ''
                ];

                // 记录流水
            } elseif ($res['result'] == 'FAILED') {
                throw new \Exception('分账回退失败：' . json_encode($res, JSON_UNESCAPED_UNICODE));
            }
        } catch (\Exception $exception) {
            $update = [
                'profit_sharing_status' => DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_FAIL,
                'profit_sharing_error' => $exception->getMessage()
            ];
        }

        // 记录调用结果
        Db::transaction(function () use ($update, $params, $res, $log, $data) {
            app()->make(DeliveryProfitSharingStatusRepository::class)->updateByWhere([
                'order_id' => $log['order_id']
            ], $update);

            if ($update['profit_sharing_status'] == DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_SUCCESS) {
                $order = app()->make(StoreOrderRepository::class)->getWhere([
                    'order_id' => $log['order_id']
                ]);

                $where = [
                    'order_sn' => $order->order_sn,
                    'type' => OrderFlow::FLOW_TYPE_IN,
                    'amount' => '+' . $data['return_amount'],
                    'remark' => $this->getTitle($data['platform_source']),
                    'mer_id' => $data['mer_id'],
                    'mch_id' => $data['mch_id'],
                    'is_del' => OrderFlow::DELETE_FALSE,
                    'profit_sharing_id' => $res['order_id']
                ];
                app()->make(OrderFlowRepository::class)->createOrUpdate($where, array_merge([
                    'create_time' => date('Y-m-d H:i:s')
                ], $where));
            }

            app()->make(DeliveryProfitSharingLogsRepository::class)->create([
                'type' => DeliveryProfitSharingLogs::RETURN_ORDERS_TYPE,
                'out_order_no' => $params['out_order_no'],
                'request' => json_encode($params, JSON_UNESCAPED_UNICODE),
                'response' => json_encode($res, JSON_UNESCAPED_UNICODE),
                'order_id' => $log['order_id'],
                'transaction_id' => $log['transaction_id']
            ]);
        });
    }

    /**
     * 获取标题
     *
     * @param $platformSource
     *
     * @return string
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/15 10:55
     */
    protected function getTitle($platformSource)
    {
        $title = '';
        switch ($platformSource) {
            case StoreOrder::PLATFORM_SOURCE_BACK_FLOW:
            case    StoreOrder::PLATFORM_SOURCE_AD:
                $title = OrderFlow::PROFIT_SHARING_RETURN_CN;
                break;
            case    StoreOrder::PLATFORM_SOURCE_NATURE:
                $title = OrderFlow::SALE_AFTER_PROFIT_SHARING_RETURN_CN;
                break;
        }
        
        return $title;
    }
}