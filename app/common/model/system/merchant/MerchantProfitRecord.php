<?php

namespace app\common\model\system\merchant;

use app\common\model\BaseModel;
use app\common\model\store\order\StoreOrder;

class MerchantProfitRecord extends BaseModel
{
    /**
     * 状态：1-未生效，2-已生效
     */
    const STATUS_NOT_VALID = 1;
    const STATUS_VALID = 2;

    /**
     * @return string
     * @day 2020-03-30
     */
    public static function tablePk(): string
    {
        return 'profit_record_id';
    }

    /**
     * @return string
     * @day 2020-03-30
     */
    public static function tableName(): string
    {
        return 'merchant_profit_record';
    }

    public function storeOrder()
    {
        return $this->hasOne(StoreOrder::class,'order_id','order_id');
    }
    public function orderMerchant(){
        return $this->hasOne(Merchant::class,'mer_id','order_mer_id');
    }
    public function profitMerchant(){
        return $this->hasOne(Merchant::class,'mer_id', 'profit_mer_id');
    }
}