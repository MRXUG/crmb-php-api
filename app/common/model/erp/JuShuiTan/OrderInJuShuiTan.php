<?php


namespace app\common\model\erp\JuShuiTan;


use app\common\model\store\order\StoreOrder;

class OrderInJuShuiTan
{
    /**
     * shop_status
     *自研商城系统订单状态：
     * 等待买家付款=WAIT_BUYER_PAY，
     * 等待卖家发货=WAIT_SELLER_SEND_GOODS（传此状态时实际支付金额即pay节点支付金额=应付金额ERP才会显示已付款待审核）,
     * 等待买家确认收货=WAIT_BUYER_CONFIRM_GOODS,
     * 交易成功=TRADE_FINISHED,
     * 付款后交易关闭=TRADE_CLOSED,
     * 付款前交易关闭=TRADE_CLOSED_BY_TAOBAO；可更新
     * @param array $orderInfo
     * @return string
     */
    public function getShopStatus(array $orderInfo) :string
    {
        if($orderInfo['paid'] == 1 && $orderInfo['is_del']){
            return "TRADE_CLOSED";
        }
        switch ($orderInfo['status']){
            case StoreOrder::ORDER_STATUS_BE_SHIPPED:
                if ($orderInfo['paid'] == 0){
                    if($orderInfo['is_del'] == 0){
                        return "WAIT_BUYER_PAY";
                    }else{
                        return "TRADE_CLOSED_BY_TAOBAO";
                    }
                }else{
                    return "WAIT_SELLER_SEND_GOODS";
                }
            case StoreOrder::ORDER_STATUS_BE_RECEIVE:
                return "WAIT_BUYER_CONFIRM_GOODS";
            case StoreOrder::ORDER_STATUS_REPLY:
            case StoreOrder::ORDER_STATUS_SUCCESS:

            case StoreOrder::ORDER_STATUS_REFUNDING:
            case StoreOrder::ORDER_STATUS_REFUND_ERROR:
            case StoreOrder::ORDER_STATUS_REFUND:
            default:
                return "TRADE_FINISHED";
        }
    }

    /**
     * 退款、退货退款
    1.发货前的订单有退款，可以先上传售后仅退款类型，确认售后单之后调用订单上传接口，items节点refund_status传success，单据在发货的时候，即可拦截对应退款商品
    2.若有整单商品都需要退款，建议直接调用订单取消接口
    3.若发货前的单据，不想传输售后单，可以直接通过订单上传items节点refund_status字段进行拦截发货商品
     *
     * 如果后续有：发起换货、申请补发
     * 再调用售后接口
     */
    public function getRefundStatus(array $orderInfo){
        if(!in_array($orderInfo['status'], [StoreOrder::ORDER_STATUS_REFUNDING, StoreOrder::ORDER_STATUS_REFUND_ERROR, StoreOrder::ORDER_STATUS_REFUND])){
            return "";
        }
        if($orderInfo['is_del'] == 1){
            return "closed";
        }
        switch ($orderInfo['status']){
            case StoreOrder::ORDER_STATUS_REFUND_ERROR:
            case StoreOrder::ORDER_STATUS_REFUNDING:
                return "waiting";
            case StoreOrder::ORDER_STATUS_REFUND:
            default:
                return "success";
        }
    }
}