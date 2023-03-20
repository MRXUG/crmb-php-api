<?php


namespace app\common\dao\store\order;


use app\common\dao\BaseDao;
use app\common\model\store\order\OrderFlow;

class OrderFlowDao extends BaseDao
{
    protected function getModel(): string
    {
        return OrderFlow::class;
    }
    public function search($where)
    {
        $query = OrderFlow::getDB();
        $query->when(isset($where['order_sn']) && $where['order_sn'],function($query)use($where){
            $query->where('order_sn',$where['order_sn']);
        });
        return $query;
    }

    /**
     * @param  array  $where
     * @return mixed
     */
    public function searchWithOrder(array $where = [])
    {
        return OrderFlow::getDB()->alias('OF')
            ->join('StoreOrder SO', 'SO.order_sn = OF.order_sn')
            ->join('MerchantGoodsPayment MGP', 'MGP.order_id = SO.order_id')
            ->join('Merchant M', 'M.mer_id = MGP.mer_id')
            ->when(isset($where['merchant_source']) && $where['merchant_source'] !== '',function ($query)use ($where){
                $query->where('SO.merchant_source', $where['merchant_source']);
            })
            ->when(isset($where['order_sn']) && $where['order_sn'] !== '',function ($query)use ($where){
                $query->where('SO.order_sn', $where['order_sn']);
            })
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
            ->field('MGP.*,OF.*,
            M.mer_id,M.mer_name,
            SO.order_id,SO.status AS order_status,SO.order_sn,SO.platform_source,
            SO.merchant_source,SO.pay_price,SO.pay_time,SO.create_time AS order_create_time')
            ->order('MGP.payment_id DESC,OF.trans_id ASC');
    }
}