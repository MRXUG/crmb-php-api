<?php


namespace app\common\model\store\order;


use app\common\model\BaseModel;

class OrderFlow extends BaseModel
{
    /**
     * 收支类型：1-收入，2-暂存，3-支出
     */
    const FLOW_TYPE_IN = 1;
    const FLOW_TYPE_PROFIT_SHARING = 2;
    const FLOW_TYPE_OUT = 3;
    
    
     // 删除成功
    const DELETE_TRUE = 1;
    const DELETE_FALSE = 0;

    const FLOW_TYPE_TEXT = [
        self::FLOW_TYPE_IN             => '收入',
        self::FLOW_TYPE_PROFIT_SHARING => '暂存/分账',
        self::FLOW_TYPE_OUT            => '支出',
    ];
    public static function getFlowTypeText(int $type):string
    {
        return self::FLOW_TYPE_TEXT[$type] ?? '';
    }


    const PROFIT_SHARING_CN = '平台押款';
    const FLOW_TYPE_IN_CN = '发货+24小时解冻%s';
    const FLOW_TYPE_IN_NATURE = '发货+24小时解冻';
    const PROFIT_SHARING_RETURN_CN = '售后押款回退';
    const SALE_AFTER_PROFIT_SHARING_RETURN_CN = '售后押款回退';
    const SALE_AFTER_REFUND_CN = '售后订单退款';
    const NATURAL_FLOW_PROFIT_SHARING_CN = '服务费分账-自然流量';
    const BACK_FLOW_PROFIT_SHARING_REFUND_CN = '收货+15天押款回退服务费分账-回流流量';
    const REFUND_PROFIT_SHARING_CN = '收货+15天押款回退';
    
    
    public static function tablePk(): ?string
    {
        return 'trans_id';
    }

    public static function tableName(): string
    {
        return 'order_flow';
    }
}