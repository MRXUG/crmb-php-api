<?php

namespace app\common\model\system\merchant;

use app\common\model\BaseModel;

class MerchantProfitDayLog extends BaseModel
{

    public static function tablePk(): string
    {
        return 'profit_id';
    }

    public static function tableName(): string
    {
        return 'merchant_profit_day_log';
    }
}