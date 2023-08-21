<?php


namespace app\common\model\delivery;


use app\common\model\BaseModel;

class DeliveryProfitSharingStatusPart extends BaseModel
{


    public static function tablePk(): ?string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'delivery_profit_status_part';
    }
}