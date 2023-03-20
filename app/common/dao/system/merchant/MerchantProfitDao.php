<?php

namespace app\common\dao\system\merchant;

use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\system\merchant\MerchantProfit;

class MerchantProfitDao extends BaseDao
{

    /**
     * @return BaseModel
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return MerchantProfit::class;
    }
}