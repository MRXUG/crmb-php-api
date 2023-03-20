<?php


namespace crmeb\listens;


use app\common\model\delivery\DeliveryProfitSharingStatus;
use app\common\repositories\store\order\StoreOrderRepository;
use crmeb\interfaces\ListenerInterface;

/**
 * 订单发货后监听事件
 * Class OrderDeliveryListen
 * @package crmeb\listens
 */
class OrderDeliveryListen implements ListenerInterface
{
    public function handle($params): void
    {
        $order = $params['order'];
        // 区分流量标识-记录佣金比例
        list($depositRate, $profitSharingRate) = app()
            ->make(StoreOrderRepository::class)
            ->switchOrderPlatformSource($order);
        DeliveryProfitSharingStatus::getDB()->insert([
            'order_id' => $order['order_id'],
            'profit_sharing_status' => DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_DEFAULT,
            'change_time' => date('Y-m-d H:i:s'),
            'mer_id' => $order['mer_id'],
            'mer_mch_id' => merchantConfig($order['mer_id'], 'pay_routine_mchid'),
            'total' => bcmul($order['pay_price'], 100),
            'app_id' => $order['appid'],
            'profit_sharing_rate' => $profitSharingRate,
            'deposit_rate' => $depositRate,
            'amount' => bcmul(bcmul($order['pay_price'], 100), $depositRate > 0 ? $depositRate : $profitSharingRate),
            'order_sn' => $order['order_sn'],
            'platform_source' => $order['platform_source']
        ]);
    }
}