<?php


namespace crmeb\listens;


use app\common\model\delivery\DeliveryProfitSharingLogs;
use app\common\model\delivery\DeliveryProfitSharingStatus;
use app\common\model\store\order\OrderFlow;
use app\common\repositories\delivery\DeliveryProfitSharingLogsRepository;
use app\common\repositories\delivery\DeliveryProfitSharingStatusRepository;
use app\common\repositories\store\order\OrderFlowRepository;
use crmeb\interfaces\ListenerInterface;
use crmeb\services\TimerService;
use crmeb\services\WechatService;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;

class ProfitSharingUnfreezeListen extends TimerService implements ListenerInterface
{
    protected string $name = "订单资金解冻：" . __CLASS__;

    public function handle($event): void
    {
        $this->tick(1000 * 60 * 1, function () {
             \think\facade\Log::info($this->name.'_start：'.date('Y-m-d H:i:s'));
            // 分账分账后-解冻商户资金
            /**
             * @var DeliveryProfitSharingStatusRepository $app
             */
            $app = app()->make(DeliveryProfitSharingStatusRepository::class);

            $limit = 50;
            $maxOrderId = 0;
            while (true) {
                $where = [
                    [
                        'profit_sharing_status',
                        '=',
                        DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_SUCCESS
                    ]
                ];
                if ($maxOrderId) {
                    array_push($where, ['order_id', '>', $maxOrderId]);
                }

                // 获取未解冻的订单
                $data = $app->getUnfreezeOrders($limit, $where);
                if (empty($data)) {
                    break;
                }

                // 查询分账记录
                $logs = app()
                    ->make(DeliveryProfitSharingLogsRepository::class)
                    ->getProfitSharingOrder('order_id', array_column($data, 'order_id'));
                $logByKeys = array_column($logs, null, 'order_id');
                foreach ($data as $value) {
                    // 解冻资金
                    $this->unfreezeOrder($value, $logByKeys[$value['order_id']]);
                    $maxOrderId = $value['order_id'];
                    // sleep(1);
                }
            }
             \think\facade\Log::info($this->name.'_end：'.date('Y-m-d H:i:s'));
        });
    }

    /**
     * 解冻资金
     *
     * @param $value
     * @param $data
     *
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/10 17:02
     */
    protected function unfreezeOrder($value, $data)
    {
        $update = [
            'unfreeze_status' => DeliveryProfitSharingStatus::PROFIT_SHARING_UNFREEZE_ING,
            'profit_sharing_error' => ''
        ];

        $res = [];

        $outOrderNo = json_decode($data['response'], true)['order_id'];
        $params = [
            'transaction_id' => $data['transaction_id'],
            'out_order_no' => $outOrderNo,
            'description' => '解冻全部剩余资金'
        ];


        try {
            $make = WechatService::getMerPayObj($value['mer_id'], $value['app_id']);
            $res = $make->profitSharing()->profitSharingUnfreeze($params);
            Log::info('res:' . json_encode($res));
            $this->handleStatus($res, $update);
        } catch (ValidateException $exception) {
            $update = [
                'unfreeze_status' => DeliveryProfitSharingStatus::PROFIT_SHARING_UNFREEZE_FAIL,
                'profit_sharing_error' => $exception->getMessage()
            ];
        }

        Db::transaction(function () use ($value, $update, $data, $params, $res) {
            app()->make(DeliveryProfitSharingStatusRepository::class)->updateByWhere([
                'order_id' => $value['order_id']
            ], $update);

            app()->make(DeliveryProfitSharingLogsRepository::class)->create([
                'type' => DeliveryProfitSharingLogs::UNFREEZE_TYPE,
                'out_order_no' => $params['out_order_no'],
                'request' => json_encode($params, JSON_UNESCAPED_UNICODE),
                'response' => json_encode($res, JSON_UNESCAPED_UNICODE),
                'order_id' => $data['order_id'],
                'transaction_id' => $data['transaction_id']
            ]);
        });
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