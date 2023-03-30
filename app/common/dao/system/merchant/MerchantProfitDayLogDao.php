<?php

namespace app\common\dao\system\merchant;

use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\system\merchant\MerchantProfitDayLog;

class MerchantProfitDayLogDao extends BaseDao
{

    protected function getModel(): string
    {
        return MerchantProfitDayLog::class;
    }

    public function search(string $fields, array $where = [])
    {
        $query = MerchantProfitDayLog::getDB()
            ->field($fields)
            ->where($where)
            ->order('profit_id DESC');
        return $query;
    }
}