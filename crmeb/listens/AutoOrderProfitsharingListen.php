<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace crmeb\listens;

use app\common\model\delivery\DeliveryProfitSharingLogs;
use app\common\model\delivery\DeliveryProfitSharingStatus;
use app\common\repositories\delivery\DeliveryProfitSharingLogsRepository;
use app\common\repositories\delivery\DeliveryProfitSharingStatusRepository;
use app\common\repositories\store\order\StoreOrderRepository;
use crmeb\interfaces\ListenerInterface;
use crmeb\services\TimerService;
use crmeb\services\WechatService;
use think\db\exception\DbException;
use think\exception\ValidateException;
use think\facade\Db;
use think\facade\Log;

class AutoOrderProfitsharingListen extends TimerService implements ListenerInterface
{
    protected string $name = "发货T+1开始分账：" . __CLASS__;

    public function handle($params): void
    {
//        echo '分账检测已开启' . PHP_EOL;1000 * 15 * 1
        $this->tick(1000 * 60 * 10, function () {
            // 新的分佣逻辑-订单发货后+24小时发起70%分佣
            $limit = 50;
            /**
             * @var DeliveryProfitSharingStatusRepository $repository
             */
            $repository = app()->make(DeliveryProfitSharingStatusRepository::class);
            $where      = [
                [
                    'change_time',
                    '<',
                    // date('Y-m-d H:i:s', time() - 86400),
                    date('Y-m-d H:i:s', time() - 300), // 临时改为5分钟
                ],
            ];
            // 查询已发货的订单
            $data = $repository->getDeliveryPrepareProfitSharingOrder($limit, $where);
            if (empty($data)) {
                return;
            }
            $dataByKeys = array_column($data, null, 'order_id');
            //查询订单
            /** @var StoreOrderRepository $ordersRep */
            $ordersRep = app()->make(StoreOrderRepository::class);
            $orders    = $ordersRep->getStoreOrderByWhereIn('order_id', array_keys($dataByKeys));
            if (empty($orders)) {
                return;
            }
            foreach ($orders as $order) {
                Log::info("订单处理发货后分账_start:" . $order['order_id'].',time:'.date('Y-m-d H:i:s'));
                $item = $dataByKeys[$order['order_id']];
                if ($item['amount'] > 0) {
                    // 请求分佣
                    $this->profitSharingOrders($order, $dataByKeys[$order['order_id']]);
                    Log::info("订单处理发货后分账_end:" . $order['order_id'].',time:'.date('Y-m-d H:i:s'));
                } else {
                    // 完结分账 理解完后 无用
                    $this->unfreeze($order, $dataByKeys[$order['order_id']]);
                }
            };
        });
    }

    /**
     * 解冻商户资金
     *
     * @param $order
     * @param $data
     *
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/14 18:54
     */
    protected function unfreeze($order, $data)
    {
        $update = [
            'unfreeze_status'      => DeliveryProfitSharingStatus::PROFIT_SHARING_UNFREEZE_ING,
            'profit_sharing_error' => '',
        ];

        $params = [
            'transaction_id' => $order['transaction_id'],
            'out_order_no'   => $order['order_sn'],
            'description'    => '解冻全部剩余资金',
        ];

        $res = [];
        try {
            $make = WechatService::getMerPayObj($order['mer_id'], $order['appid']);
            $res  = $make->profitSharing()->profitSharingUnfreeze($params);
            \think\facade\Log::info('res:' . json_encode($res));
            $this->handleStatus($res, $update);
        } catch (ValidateException $exception) {
            $update = [
                'unfreeze_status'      => DeliveryProfitSharingStatus::PROFIT_SHARING_UNFREEZE_FAIL,
                'profit_sharing_error' => $exception->getMessage(),
            ];
        }

        Db::transaction(function () use ($order, $update, $data, $params, $res) {
            app()->make(DeliveryProfitSharingStatusRepository::class)->updateByWhere([
                'order_id' => $order['order_id'],
            ], $update);

            app()->make(DeliveryProfitSharingLogsRepository::class)->create([
                'type'           => DeliveryProfitSharingLogs::UNFREEZE_TYPE,
                'out_order_no'   => $params['out_order_no'],
                'request'        => json_encode($params, JSON_UNESCAPED_UNICODE),
                'response'       => json_encode($res, JSON_UNESCAPED_UNICODE),
                'order_id'       => $data['order_id'],
                'transaction_id' => $order['transaction_id'],
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

    /**
     * 分佣
     *
     * @param $order
     * @param $data
     *
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/3 14:32
     */
    protected function profitSharingOrders($order, $data)
    {
        /**
         * @var DeliveryProfitSharingStatusRepository $app
         */
        $app    = app()->make(DeliveryProfitSharingStatusRepository::class);
        $amount = $data['amount'];
        // 重新发起分账需要更换分账单号;
        $update = [
            // 分佣改为处理中状态
            'profit_sharing_status' => DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_ING,
            // 分佣异常消息
            'profit_sharing_error'  => '',
            // 商户号
            'mch_id'                => '', //获取接受分账商户号（平台商户）
            // 分账金额
            'amount'                => $amount,
        ];

        $res = $params = [];
        try {
            // 获取分账接受商户号
            $mchId            = $app->getProfitSharingAcceptMchId([merchantConfig($order['mer_id'], 'pay_routine_mchid')]);
            $update['mch_id'] = $mchId;
            // 获取商户名称
            $merName = $app->getMchName($mchId);
            // 获取商户配置
            $make = WechatService::getMerPayObj($order['mer_id'], $order['appid']);
            // 添加分账接收方
            $app->addProfitSharingReceivers($make, $mchId, $merName);

            $params = [
                'transaction_id'   => $order['transaction_id'],
                'out_order_no'     => $order['order_sn'],
                'unfreeze_unsplit' => true, //解冻剩余金额到商户平台
                'receivers'        => [
                    [
                        'type'        => 'MERCHANT_ID',
                        // 商户号
                        'account'     => (string) $mchId,
                        'amount'      => (int) $amount,
                        'description' => "商家转帐",
                    ],
                ],
            ];

            \think\facade\Log::info($this->name . json_encode($params));
            // 请求分账
            $res                             = $make->profitSharing()->profitSharingOrders($params);
            $update['profit_sharing_result'] = json_encode($res, JSON_UNESCAPED_UNICODE);
            // 处理分账状态 请求未报错情况下就是分账中
        } catch (\Throwable $e) {
            $update['profit_sharing_error'] = $e->getMessage();
        }
        // 记录分佣日志
        \think\facade\Log::info($this->name . json_encode($update));
        try {
            Db::transaction(function () use ($app, $order, $update, $params, $res) {
                // 记录本次分佣结果
                $app->updateByWhere([
                    'order_id' => $order['order_id'],
                ], $update);

                app()->make(DeliveryProfitSharingLogsRepository::class)->create([
                    'type'           => DeliveryProfitSharingLogs::PROFIT_SHARING_TYPE,
                    'out_order_no'   => $order['order_sn'],
                    'request'        => json_encode($params, JSON_UNESCAPED_UNICODE),
                    'response'       => json_encode($res, JSON_UNESCAPED_UNICODE),
                    'order_id'       => $order['order_id'] ?? '',
                    'transaction_id' => $order['transaction_id'] ?? '',
                ]);
            });
        } catch (DbException $e) {
            \think\facade\Log::error($order['order_id'] . '记录发货分佣失败：' . $e->getMessage() . json_encode($update, JSON_UNESCAPED_UNICODE));
        }
    }
}
