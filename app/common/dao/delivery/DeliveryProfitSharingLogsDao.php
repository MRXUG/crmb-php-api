<?php


namespace app\common\dao\delivery;


use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\delivery\DeliveryProfitSharingLogs;

class DeliveryProfitSharingLogsDao extends BaseDao
{
    protected function getModel(): string
    {
        return DeliveryProfitSharingLogs::class;
    }
    
    public function getProfitSharingOrder($key, $values)
    {
        return DeliveryProfitSharingLogs::getDB()->whereIn($key, $values)
            ->order('profit_sharing_id','desc')
            ->where(['type' => DeliveryProfitSharingLogs::PROFIT_SHARING_TYPE])
            ->where('response','<>','[]')
            ->group('order_id')
            ->select()
            ->toArray();
    }

    /**
     * 获取分佣记录通过订单ID
     *
     * @param $orderId
     *
     * @return array|\think\Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/11 17:00
     */
    public function getProfitSharingOrderByOrderId($orderId)
    {
        return DeliveryProfitSharingLogs::getDB()->where([
            'type' => DeliveryProfitSharingLogs::PROFIT_SHARING_TYPE,
            'order_id' => $orderId
        ])->order('profit_sharing_id','desc')->find();
    }

    /**
     * 获取解冻中的日志
     * 
     * @param $key
     * @param $values
     *
     * @return mixed
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/11 17:36
     */
    public function getUnfreezeIngOrderLog($key, $values)
    {
        return DeliveryProfitSharingLogs::getDB()
            ->whereIn($key, $values)
            ->order('profit_sharing_id', 'desc')
            ->where(['type' => DeliveryProfitSharingLogs::UNFREEZE_TYPE])
            ->group('order_id')
            ->select()
            ->toArray();
    }

    /**
     * 获取回退中的日志
     *
     * @param $key
     * @param $values
     *
     * @return mixed
     * @author  zouxiuhui <zouxiuhui@vchangyi.com>
     * @date    2023/3/11 17:37
     */
    public function getProfitSharingReturnLog($key, $values)
    {
         return DeliveryProfitSharingLogs::getDB()
            ->whereIn($key, $values)
            ->order('profit_sharing_id', 'desc')
            ->where(['type' => DeliveryProfitSharingLogs::RETURN_ORDERS_TYPE])
            ->group('order_id')
            ->select()
            ->toArray();
    }
}