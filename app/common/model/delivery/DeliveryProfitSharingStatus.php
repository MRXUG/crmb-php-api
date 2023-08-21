<?php


namespace app\common\model\delivery;


use app\common\model\BaseModel;

class DeliveryProfitSharingStatus  extends BaseModel
{
    // 待请求分账
    const PROFIT_SHARING_STATUS_DEFAULT  = 0;
    // 进行中
    const PROFIT_SHARING_STATUS_ING = 1;
    // 分佣失败
    const PROFIT_SHARING_STATUS_FAIL = 2;
    // 分佣成功
    const PROFIT_SHARING_STATUS_SUCCESS = 3;
    // 分账回退处理中
    const PROFIT_SHARING_STATUS_RETURN_ING = 4;
    // 分账回退失败
    const PROFIT_SHARING_STATUS_RETURN_FAIL = 5;
    // 分账回退成功
    const PROFIT_SHARING_STATUS_RETURN_SUCCESS = 6;
    // 分账回退部分 成功，用于记录部分回退状态，计算退款等。例如自然流量不回退，回流流量部分回退
    const PROFIT_SHARING_STATUS_RETURN_SUCCESS_PART = 7;
    // 用户退款中断处理分账
    const PROFIT_SHARING_STATUS_RETURN_FNIAL = 6;
    
    
    // 0-资金未解冻 1-解冻中 2-解冻失败 3-解冻成功
    const PROFIT_SHARING_UNFREEZE_DEFAULT = 0;
    const PROFIT_SHARING_UNFREEZE_ING = 1;
    const PROFIT_SHARING_UNFREEZE_FAIL = 2;
    const PROFIT_SHARING_UNFREEZE_SUCCESS = 3;
    
    // 删除状态 0-未删除 1-已删除
    const DELETE_DEFAULT = 0;
    const DELETE_TRUE = 1;

    public static function tablePk(): ?string
    {
        return 'order_sn';
    }

    public static function tableName(): string
    {
        return 'delivery_profit_sharing_status';
    }
}