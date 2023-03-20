<?php

namespace app\common\dao\system\merchant;

use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\system\merchant\MerchantProfitRecord;

class MerchantProfitRecordDao extends BaseDao
{

    /**
     * @return BaseModel
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return MerchantProfitRecord::class;
    }
    public function search(string $fields, array $where = [])
    {
        $query = MerchantProfitRecord::getDB()
            ->field($fields)
            ->where('status', MerchantProfitRecord::STATUS_VALID)
            ->where('profit_money','>',0)
            ->where($where)
            ->order('profit_record_id DESC');
        return $query;
    }
}