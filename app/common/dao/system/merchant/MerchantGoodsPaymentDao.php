<?php

namespace app\common\dao\system\merchant;

use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\system\merchant\MerchantGoodsPayment;
use think\db\Query;

class MerchantGoodsPaymentDao extends BaseDao
{

    /**
     * @return BaseModel
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return MerchantGoodsPayment::class;
    }

    /**
     * @param  array  $where
     * @return mixed
     */
    public function search(array $where = [])
    {
        return MerchantGoodsPayment::getDB()->alias('MGP')
            ->join('StoreOrder S', 'S.order_id = MGP.order_id')
            ->where(function ($query) use ($where) {
                if (isset($where['merchant_source']) && $where['merchant_source'] !== '' ) {
                    $query->where('S.merchant_source', $where['merchant_source']);
                }
                if (isset($where['order_sn']) && $where['order_sn'] !== '' ) {
                    $query->where('S.order_sn', $where['order_sn']);
                }
            })
            ->join('Merchant M', 'M.mer_id = MGP.mer_id')
            ->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
                getModelTime($query, $where['date'], 'MGP.latest_flow_time');
            })
            ->when(isset($where['settlement_status']) && $where['settlement_status'] !== '', function ($query) use ($where) {
                    $query->where('MGP.settlement_status', $where['settlement_status']);
                })
            ->when(isset($where['service_fee_status']) && $where['service_fee_status'] !== '', function ($query) use ($where) {
                    $query->where('MGP.service_fee_status', $where['service_fee_status']);
                })
            ->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
                $query->where('MGP.mer_id', $where['mer_id']);
            })
            ->when(isset($where['platform_mer_id']) && $where['platform_mer_id'] !== '', function ($query) use ($where) {
                    $query->where('MGP.platform_mer_id', $where['platform_mer_id']);
                })
            ->field('MGP.*,M.mer_id,M.mer_name,S.order_id,S.status AS order_status,S.order_sn,S.platform_source,
            S.merchant_source,S.pay_price,S.pay_time,S.create_time AS order_create_time')
            ->order('MGP.payment_id DESC');
    }

    /**
     * @param  int  $orderId
     * @return bool
     */
    public function orderExists(int $orderId)
    {
        return $this->existsWhere(['order_id' => $orderId]);
    }
}