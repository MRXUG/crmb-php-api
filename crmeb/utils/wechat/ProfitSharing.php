<?php

namespace crmeb\utils\wechat;

use app\common\model\delivery\DeliveryProfitSharingStatus;
use app\common\model\store\order\StoreRefundOrder;
use app\common\model\store\RefundTask;
use crmeb\jobs\SplitReturnResultJob;
use crmeb\services\WechatService;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\exception\ValidateException;
use think\facade\Queue;

/**
 * 分账调用封装
 *
 * @author x
 */
class ProfitSharing
{
    /**
     * 分账退回调用
     * @param int $refundOrderId 退款订单 id
     * @return void
     * @throws null
     */
    public static function refund(int $refundOrderId): void
    {
        # 获取退款订单信息
        $refundOrder = StoreRefundOrder::getDB()
            ->alias('a')
            ->field(['a.*', 'b.appid', 'b.transaction_id', 'b.order_sn'])
            ->leftJoin('eb_store_order b', 'a.order_id = b.order_id')
            ->where('a.refund_order_id', $refundOrderId)
            ->find();
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
        # 创建用来定时循环获取回退分账结果的基础数据
        $refundBaseData = [];
        # 循环调用分账回退
        foreach ($deliveryProfit as $item) {
            $refundBaseData[] = [
                'merId' => $item['mer_id'],
                'appId' => $item['app_id'],
                'outOrderNo' => $item['order_sn'],
                'outReturnNo' => $item['order_sn'],
                'returnMchId' => $item['mch_id'],
                'amount' => $item['amount'],
            ];
            # 跳过分佣中确保不重复提交申请
            if ($item->getAttr('profit_sharing_status') == DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_ING){
                continue;
            }
            # 商户支付对象 调用分账退回 保证所有的 分账都是在申请回退状态
            WechatService::getMerPayObj($item['mer_id'], $item['app_id'])
                ->profitSharing()
                ->profitSharingReturn([
                    'out_order_no' => $item['order_sn'],
                    'out_return_no' => $item['order_sn'],
                    'return_mchid' => $item['mch_id'],
                    'amount' => $item['amount'],
                    'description' => '用户退款 回退'
                ]);
            # 将发起退回的订单标记为正在退回
            DeliveryProfitSharingStatus::getDB()->where([
                'order_id' => $item['order_id'],
                'mch_id' => $item['mch_id']
            ])->update([
                'profit_sharing_status' => DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_RETURN_ING
            ]);
        }
        # 调用分账退回定时查询
        RefundTask::getDB()->save([
            'refund_order_id' => $refundOrder['refund_order_id'],
            'order_id' => $refundOrder['order_id'],
            'mer_id' => $refundOrder['mer_id'],
            'app_id' => $refundOrder['appid'],
            'param' => json_encode($refundBaseData, JSON_UNESCAPED_UNICODE),
            'create_time' => date("Y-m-d H:i:s"),
            'transaction_id' => $refundOrder['transaction_id'],
            'order_sn' => $refundOrder['order_sn']
        ]);
    }
}
