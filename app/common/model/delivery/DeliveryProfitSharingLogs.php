<?php


namespace app\common\model\delivery;


use app\common\model\BaseModel;

class DeliveryProfitSharingLogs extends BaseModel
{
    // 1-分佣 2-解冻 3-回退
    const PROFIT_SHARING_TYPE = 1;
    const UNFREEZE_TYPE = 2;
    const RETURN_ORDERS_TYPE = 3;
  
    
    public static function tablePk(): ?string
    {
        return 'profit_sharing_id';
    }

    public static function tableName(): string
    {
        return 'delivery_profit_sharing_logs';
    }
}