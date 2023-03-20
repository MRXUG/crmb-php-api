<?php


namespace crmeb\listens;


use app\common\model\delivery\DeliveryProfitSharingLogs;
use app\common\model\delivery\DeliveryProfitSharingStatus;
use app\common\model\store\order\OrderFlow;
use app\common\model\store\order\StoreOrder;
use app\common\repositories\delivery\DeliveryProfitSharingLogsRepository;
use app\common\repositories\delivery\DeliveryProfitSharingStatusRepository;
use app\common\repositories\store\order\OrderFlowRepository;
use app\common\repositories\system\merchant\MerchantGoodsPaymentRepository;
use crmeb\interfaces\ListenerInterface;
use crmeb\services\TimerService;
use crmeb\services\WechatService;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;

class UpdateDeliveryOrderUnfreezeStatus extends TimerService implements ListenerInterface
{
    protected string $name = "更新订单解冻状态：" . __CLASS__;

    public function handle($event): void
    {
        // 更新订单解冻状态
        $this->tick(1000 * 60 * 1, function () {
            \think\facade\Log::info($this->name.'_start：'.date('Y-m-d H:i:s'));
            $maxOrderId = 0;
            $limit = 50;
            while (true) {
                $where = [
                    [
                        'unfreeze_status',
                        '=',
                        DeliveryProfitSharingStatus::PROFIT_SHARING_UNFREEZE_ING
                    ]
                ];
                if ($maxOrderId) {
                    array_push($where, ['order_id', '>', $maxOrderId]);
                }
                $data = app()->make(DeliveryProfitSharingStatusRepository::class)->getUnfreezeIngOrders($limit, $where);
                if (empty($data)) {
                    break;
                }

                $data = array_column($data, null, 'order_id');
                // 获取解冻的参数
                $logs = app()
                    ->make(DeliveryProfitSharingLogsRepository::class)
                    ->getUnfreezeIngOrderLog('order_id', array_keys($data));
                // 查询解冻结果
                foreach ($logs as $log) {
                    $this->getUnfreezeResult($log, $data[$log['order_id']]);
                    // sleep(1);
                    $maxOrderId = $log['order_id'];
                }
            }
            \think\facade\Log::info($this->name.'_end：'.date('Y-m-d H:i:s'));
        });
    }

    /**
     * 查询订单解冻结果
     *
     * @param $log
     * @param $data
     *
     * @return bool
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/11 14:50
     */
    protected function getUnfreezeResult($log, $data)
    {
        $res = [];
        $params = [
            'transaction_id' => $log['transaction_id'],
            'out_order_no' => $log['out_order_no']
        ];

        $update = [
            'unfreeze_status' => DeliveryProfitSharingStatus::PROFIT_SHARING_UNFREEZE_ING,
            'profit_sharing_error' => ''
        ];
        try {
            // 获取商户证书配置
            $make = WechatService::getMerPayObj($data['mer_id'], $data['app_id']);
            $res = $make->profitSharing()->profitSharingResult($log['out_order_no'], $log['transaction_id']);
            // 处理分账结果
            Log::info('res:' . json_encode($res, JSON_UNESCAPED_UNICODE));
            // if ($res['state'] == 'FINISHED') {
            //     // 分账成功
            //     /* @var MerchantGoodsPaymentRepository $repo */
            //     $repo = app()->make(MerchantGoodsPaymentRepository::class);
            //     $repo->updateWhenDeliveryPlus1Day($log['order_id'], [
            //        
            //     ]);
            // } else
            if (empty($res)) {
                return true;
            }

            if ($res['state'] == 'PROCESSING') {
                // 处理中不处理
                return true;
            }
            
            $update['unfreeze_status'] = DeliveryProfitSharingStatus::PROFIT_SHARING_UNFREEZE_SUCCESS;
        } catch (ValidateException $exception) {
            $update = [
                'unfreeze_status' => DeliveryProfitSharingStatus::PROFIT_SHARING_UNFREEZE_FAIL,
                'profit_sharing_error' => $exception->getMessage()
            ];
        }

        Db::transaction(function () use ($update, $params, $res, $log,$data) {
            app()->make(DeliveryProfitSharingStatusRepository::class)->updateByWhere([
                'order_id' => $log['order_id']
            ], $update);

            app()->make(DeliveryProfitSharingLogsRepository::class)->create([
                'type' => DeliveryProfitSharingLogs::UNFREEZE_TYPE,
                'out_order_no' => $params['out_order_no'],
                'request' => json_encode($params, JSON_UNESCAPED_UNICODE),
                'response' => json_encode($res, JSON_UNESCAPED_UNICODE),
                'order_id' => $log['order_id'],
                'transaction_id' => $log['transaction_id']
            ]);

            if ($update['unfreeze_status'] == DeliveryProfitSharingStatus::PROFIT_SHARING_UNFREEZE_SUCCESS) {
                $where = [
                    'amount' => '+' . bcsub($data['total'], $data['amount']),
                    'type' => OrderFlow::FLOW_TYPE_IN,
                    'mer_id' => $data['mer_id'],
                    'mch_id' => $data['mch_id'],
                    'order_sn' => $data['order_sn'],
                    'remark' => $this->getTitleByPlatformSource($data),
                    'is_del' => OrderFlow::DELETE_FALSE,
                    'profit_sharing_id' => $res['order_id']
                ];
                app()->make(OrderFlowRepository::class)->createOrUpdate($where, array_merge([
                    'create_time' => date('Y-m-d H:i:s')
                ],$where));
            }
        });
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
                $rate = bcsub(1, $data['deposit_rate'],2);
                $title = sprintf(OrderFlow::FLOW_TYPE_IN_CN, bcmul($rate,100).'%');
                break;
        }
        
        return $title;
    }
}