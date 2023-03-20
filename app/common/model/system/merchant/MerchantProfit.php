<?php

namespace app\common\model\system\merchant;

use app\common\model\BaseModel;

class MerchantProfit extends BaseModel
{
    /**
     * @return string
     * @day 2020-03-30
     */
    public static function tablePk(): string
    {
        return 'profit_id';
    }

    /**
     * @return string
     * @day 2020-03-30
     */
    public static function tableName(): string
    {
        return 'merchant_profit';
    }
}