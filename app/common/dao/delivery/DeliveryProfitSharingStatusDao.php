<?php


namespace app\common\dao\delivery;


use app\common\dao\BaseDao;
use app\common\model\delivery\DeliveryProfitSharingStatus;

class DeliveryProfitSharingStatusDao extends BaseDao
{
    protected function getModel(): string
    {
        return DeliveryProfitSharingStatus::class;
    }

    /**
     * 获取已发货待分佣的订单
     *
     * @param $limit
     * @param $where
     *
     * @return mixed
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/7 14:23
     */
    public function getDeliveryPrepareProfitSharingOrder($limit, $where)
    {
        return DeliveryProfitSharingStatus::getDB()
            ->whereIn('profit_sharing_status', [
                DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_FAIL,
                DeliveryProfitSharingStatus::PROFIT_SHARING_STATUS_DEFAULT,
            ])->where('amount','>',0)->whereOr(function ($query) use ($where) {
                $query->whereIn('unfreeze_status', [
                    DeliveryProfitSharingStatus::PROFIT_SHARING_UNFREEZE_DEFAULT,
                    DeliveryProfitSharingStatus::PROFIT_SHARING_UNFREEZE_FAIL,
                ])
                    ->where($where)
                    ->where('is_del', DeliveryProfitSharingStatus::DELETE_DEFAULT)
                    ->where('amount', '>', 0);
            })
            ->where('is_del', DeliveryProfitSharingStatus::DELETE_DEFAULT)
            ->where($where)
            ->order('change_time DESC')
            ->group('order_id')
            ->limit($limit)
            ->select()
            ->toArray();
    }

    /**
     * 获取已发货分佣中的订单
     *
     * @param $where
     * @param $limit
     *
     * @return mixed
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/7 14:25
     */
    public function getDeliveryProfitSharingOrder($limit, $where)
    {
        return DeliveryProfitSharingStatus::getDB()
            ->group('order_id')
            ->where($where)
            ->limit($limit)
            ->select()
            ->toArray();
    }

    /**
     * 获取发货后是否分帐成功过
     *
     * @param $orderId
     *
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/7 14:26
     */
    public function getProfitSharingStatus($orderId)
    {
        $info = DeliveryProfitSharingStatus::getDB()
            ->where('order_id', $orderId)
            ->field('profit_sharing_status,mch_id,amount,unfreeze_status')
            ->find();
        return $info ? $info->toArray() : [];
    }
}