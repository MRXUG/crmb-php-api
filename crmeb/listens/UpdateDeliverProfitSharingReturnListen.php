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
use think\facade\Db;
use think\facade\Log;

class UpdateDeliverProfitSharingReturnListen extends TimerService implements ListenerInterface
{
    protected string $name = "更新分账&分账回退订单状态：" . __CLASS__;

    public function handle($event): void
    {
        $this->tick(1000*60*5, function () {
            request()->clearCache();
            // 获取分账处理中 和分账回退处理中状态
            $data = DeliveryProfitSharingStatus::getDB()->whereIn('profit_sharing_status', [
                DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_ING,
                DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_ING,
            ])->where('transaction_id','<>','')->limit(50)->select()->toArray();
            if (empty($data)) {
                return;
            }
            foreach ($data as $item) {
                $make   = WechatService::getMerPayObj($item['mer_id'], $item['app_id']);
                $status = false;
                //分账状态同步 查询分账结果
                if ($item['profit_sharing_status'] == DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_ING) {
                    $res = $make->profitSharing()->profitSharingResult($item['order_sn'], $item['transaction_id']);
                    if ($res['state'] == 'PROCESSING') {
                        return;
                    } else {
                        $status = DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_SUCCESS;
                    }
                }
                if ($item['profit_sharing_status'] == DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_ING) {
                    # 回退结果
                    $res    = $make->profitSharing()->profitSharingReturnResult($item['order_sn'], $item['order_sn']);
                    $status = DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_ING;
                    switch ($res['result']) {
                        case 'SUCCESS':
                            $status = DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_SUCCESS;
                            break;
                        case 'FAILED':
                            $status = DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_FAIL;
                            break;
                        default:
                            $status = DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_ING;
                            break;
                    }
                }
                if ($status != false) {
                    app()->make(DeliveryProfitSharingStatusRepository::class)->updateByWhere([
                        'order_id' => $item['order_id'],
                    ], ['profit_sharing_status' => $status]);
                    //分账成功
                    if ($status == DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_SUCCESS) {
                        app()->make(MerchantGoodsPaymentRepository::class)->updateWhenDeliveryPlus1Day($item['order_id'], [
                            'mchId'         => $item['mch_id'],
                            'deposit_money' => bcmul($item['total'], $item['deposit_rate']),
                        ]);
                        $where = [
                            'amount'            => $item['amount'],
                            'type'              => OrderFlow::FLOW_TYPE_PROFIT_SHARING,
                            'mer_id'            => $item['mer_id'],
                            'mch_id'            => $item['mch_id'],
                            'order_sn'          => $item['order_sn'],
                            'remark'            => $this->getTitleByPlatformSource($item),
                            'is_del'            => OrderFlow::DELETE_FALSE,
                            'profit_sharing_id' => $res['order_id'],
                        ];
                        // 记录流水
                        app()->make(OrderFlowRepository::class)->createOrUpdate($where, array_merge([
                            'create_time' => date('Y-m-d H:i:s'),
                        ], $where));
                    }
                    //分账回退成功
                    if ($status == DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_SUCCESS) {
                        $where = [
                            'order_sn'          => $item['order_sn'],
                            'type'              => OrderFlow::FLOW_TYPE_IN,
                            'amount'            => '+' . $res['amount'],
                            'remark'            => $this->getTitle($item['platform_source']),
                            'mer_id'            => $item['mer_id'],
                            'mch_id'            => $item['mch_id'],
                            'is_del'            => OrderFlow::DELETE_FALSE,
                            'profit_sharing_id' => $res['order_id'],
                        ];
                        app()->make(OrderFlowRepository::class)->createOrUpdate($where, array_merge([
                            'create_time' => date('Y-m-d H:i:s'),
                        ], $where));
                    }
                }

            }
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
            'out_order_no'  => $log['out_order_no'],
        ];
        $res = $update = [];
        try {
            $make = WechatService::getMerPayObj($data['mer_id'], $data['app_id']);
            $res  = $make->profitSharing()->profitSharingReturnResult($log['out_return_no'], $log['out_order_no']);
            // 处理结果
            if (empty($res)) {
                return true;
            }

            if ($res['result'] == 'PROCESSING') {
                return true;
            } elseif ($res['result'] == 'SUCCESS') {
                $update = [
                    'profit_sharing_status' => DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_SUCCESS,
                    'profit_sharing_error'  => '',
                ];

                // 记录流水
            } elseif ($res['result'] == 'FAILED') {
                throw new \Exception('分账回退失败：' . json_encode($res, JSON_UNESCAPED_UNICODE));
            }
        } catch (\Exception $exception) {
            $update = [
                'profit_sharing_status' => DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_FAIL,
                'profit_sharing_error'  => $exception->getMessage(),
            ];
        }

        // 记录调用结果
        Db::transaction(function () use ($update, $params, $res, $log, $data) {
            app()->make(DeliveryProfitSharingStatusRepository::class)->updateByWhere([
                'order_id' => $log['order_id'],
            ], $update);

            if ($update['profit_sharing_status'] == DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_SUCCESS) {
                $order = app()->make(StoreOrderRepository::class)->getWhere([
                    'order_id' => $log['order_id'],
                ]);

                $where = [
                    'order_sn'          => $order->order_sn,
                    'type'              => OrderFlow::FLOW_TYPE_IN,
                    'amount'            => '+' . $data['return_amount'],
                    'remark'            => $this->getTitle($data['platform_source']),
                    'mer_id'            => $data['mer_id'],
                    'mch_id'            => $data['mch_id'],
                    'is_del'            => OrderFlow::DELETE_FALSE,
                    'profit_sharing_id' => $res['order_id'],
                ];
                app()->make(OrderFlowRepository::class)->createOrUpdate($where, array_merge([
                    'create_time' => date('Y-m-d H:i:s'),
                ], $where));
            }

            app()->make(DeliveryProfitSharingLogsRepository::class)->create([
                'type'           => DeliveryProfitSharingLogs::RETURN_ORDERS_TYPE,
                'out_order_no'   => $params['out_order_no'],
                'request'        => json_encode($params, JSON_UNESCAPED_UNICODE),
                'response'       => json_encode($res, JSON_UNESCAPED_UNICODE),
                'order_id'       => $log['order_id'],
                'transaction_id' => $log['transaction_id'],
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
            case StoreOrder::PLATFORM_SOURCE_AD:
                $title = OrderFlow::PROFIT_SHARING_RETURN_CN;
                break;
            case StoreOrder::PLATFORM_SOURCE_NATURE:
                $title = OrderFlow::SALE_AFTER_PROFIT_SHARING_RETURN_CN;
                break;
        }

        return $title;
    }

    /**
     * 获取标题
     *
     * @param $data
     *
     * @return string
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/14 21:06
     */
    protected function getTitleByPlatformSource($data)
    {
        $title = '';
        switch ($data['platform_source']) {
            case StoreOrder::PLATFORM_SOURCE_NATURE:
                $title = OrderFlow::FLOW_TYPE_IN_NATURE;
                break;
            case StoreOrder::PLATFORM_SOURCE_BACK_FLOW;
            case StoreOrder::PLATFORM_SOURCE_AD:
                $rate  = bcsub(1, $data['deposit_rate'], 2);
                $title = sprintf(OrderFlow::FLOW_TYPE_IN_CN, bcmul($rate, 100) . '%');
                break;
        }

        return $title;
    }
}
