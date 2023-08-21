<?php

namespace crmeb\utils\wechat;

use app\common\dao\store\order\StoreOrderDao;
use app\common\model\delivery\DeliveryProfitSharingStatus;
use app\common\model\store\order\StoreRefundOrder;
use app\common\model\store\RefundTask;
use app\common\repositories\delivery\DeliveryProfitSharingStatusRepository;
use app\common\repositories\store\order\StoreRefundStatusRepository;
use crmeb\services\WechatService;

/**
 * 分账调用封装
 *
 * @author x
 */
class ProfitSharing
{
    /**
     * 分账退回调用 TODO 没有调用 可以删除
     * @param int $refundOrderId 退款订单 id
     * @return void
     * @throws null
     */
    public static function refundBak(int $refundOrderId): void
    {
        # 获取退款订单信息
        $refundOrder = StoreRefundOrder::getDB()
            ->alias('a')
            ->field(['a.*', 'b.appid', 'b.transaction_id', 'b.order_sn'])
            ->leftJoin('eb_store_order b', 'a.order_id = b.order_id')
            ->where('a.refund_order_id', $refundOrderId)
            ->find();

        @StoreRefundOrder::getDB()->where('refund_order_id', '=', $refundOrderId)->update([
            'status' => 4,
        ]);

        /** @var DeliveryProfitSharingStatusRepository $make */
        $make = app()->make(DeliveryProfitSharingStatusRepository::class);
        $info = $make->getProfitSharingStatus($refundOrder['order_id']);
        # 检测是否二次提交
        if (RefundTask::getInstance()->where('refund_order_id', $refundOrderId)->count('refund_task_id') > 0) {
            /** @var StoreRefundStatusRepository $statusRepository */
            $statusRepository = app()->make(StoreRefundStatusRepository::class);
            $statusRepository->status(
                $refundOrderId,
                StoreRefundStatusRepository::WECHAT_REFUND_RE_INITIATED,
                '微信退款重新发起'
            );
        }
        # 修改订单状态
        /** @var StoreOrderDao $orderDao */
        $orderDao = app()->make(StoreOrderDao::class);
        $orderDao->updateOrderStatus($refundOrder->getAttr('order_id'), 4);
        # 创建用来定时循环获取回退分账结果的基础数据
        $refundBaseData = [];
        # 判断是否立即付款
        if (!empty($info)) {
            # 获取分账状态信息表数据
            /** @var DeliveryProfitSharingStatus[] $deliveryProfit */
            $deliveryProfit = DeliveryProfitSharingStatus::getDB()
                ->where('order_id', $refundOrder['order_id'])
                ->whereIn('profit_sharing_status', [
                    DeliveryProfitSharingStatus::PROFIT_SHARING_UNFREEZE_SUCCESS, // 分佣成功
                    DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_FAIL, // 回退分佣失败
                    DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_ING, // 分佣中
                ])
                ->select();
            # 循环调用分账回退
            foreach ($deliveryProfit as $item) {
                $refundBaseData[] = [
                    'merId'       => $item['mer_id'],
                    'appId'       => $item['app_id'],
                    'outOrderNo'  => $item['order_sn'],
                    'outReturnNo' => $item['order_sn'],
                    'returnMchId' => $item['mch_id'],
                    'amount'      => $item['amount'],
                ];
                # 跳过分佣中确保不重复提交申请
                if ($item->getAttr('profit_sharing_status') == DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_ING) {
                    continue;
                }
                # 商户支付对象 调用分账退回 保证所有的 分账都是在申请回退状态
                WechatService::getMerPayObj($item['mer_id'], $item['app_id'])
                    ->profitSharing()
                    ->profitSharingReturn([
                        'out_order_no'  => $item['order_sn'],
                        'out_return_no' => $item['order_sn'],
                        'return_mchid'  => $item['mch_id'],
                        'amount'        => $item['amount'],
                        'description'   => '用户退款 回退',
                    ]);
                # 将发起退回的订单标记为正在退回
                DeliveryProfitSharingStatus::getDB()->where([
                    'order_id' => $item['order_id'],
                    'mch_id'   => $item['mch_id'],
                ])->update([
                    'profit_sharing_status' => DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_ING,
                ]);
            }
        }
        # 调用分账退回定时查询
        RefundTask::getDB()->save([
            'refund_order_id' => $refundOrder['refund_order_id'],
            'order_id'        => $refundOrder['order_id'],
            'mer_id'          => $refundOrder['mer_id'],
            'app_id'          => $refundOrder['appid'],
            'param'           => json_encode($refundBaseData, JSON_UNESCAPED_UNICODE),
            'create_time'     => date("Y-m-d H:i:s"),
            'transaction_id'  => $refundOrder['transaction_id'],
            'order_sn'        => $refundOrder['order_sn'],
        ]);
    }

    /**
     *  创建退款任务
     */

    public static function createRefundTask(int $refundOrderId): void
    {
        $isready = RefundTask::getDB()->where('refund_order_id', $refundOrderId)->find();
        if (!empty($isready)) {
            return;
        }
        # 获取退款订单信息
        $refundOrder = StoreRefundOrder::getDB()
            ->alias('a')
            ->field(['a.*', 'b.appid', 'b.transaction_id', 'b.order_sn'])
            ->leftJoin('eb_store_order b', 'a.order_id = b.order_id')
            ->where('a.refund_order_id', $refundOrderId)
            ->find();
        $deliveryProfit = DeliveryProfitSharingStatus::getDB()
            ->where('order_id', $refundOrder['order_id'])
            ->find();
        if (!empty($deliveryProfit)&&in_array($deliveryProfit['profit_sharing_status'], [DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_ING, DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_SUCCESS])) {
            # 商户支付对象 调用分账退回 保证所有的 分账都是在申请回退状态
            WechatService::getMerPayObj($deliveryProfit['mer_id'], $deliveryProfit['app_id'])
                ->profitSharing()
                ->profitSharingReturn([
                    'out_order_no'  => $deliveryProfit['order_sn'],
                    'out_return_no' => $deliveryProfit['order_sn'],
                    'return_mchid'  => $deliveryProfit['mch_id'],
                    'amount'        => $deliveryProfit['amount'],
                    'description'   => '用户退款 回退',
                ]);
            # 将发起退回的订单标记为正在退回
            DeliveryProfitSharingStatus::getDB()->where([
                'order_id' => $deliveryProfit['order_id'],
                'mch_id'   => $deliveryProfit['mch_id'],
            ])->update([
                'profit_sharing_status' => DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_ING,
                'return_amount'=> $deliveryProfit['amount']
            ]);
        }
        RefundTask::getDB()->save([
            'refund_order_id' => $refundOrder['refund_order_id'],
            'order_id'        => $refundOrder['order_id'],
            'mer_id'          => $refundOrder['mer_id'],
            'app_id'          => $refundOrder['appid'],
            'create_time'     => date("Y-m-d H:i:s"),
            'transaction_id'  => $refundOrder['transaction_id'],
            'order_sn'        => $refundOrder['order_sn'],
        ]);

    }
}
