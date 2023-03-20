<?php

namespace app\common\dao\system\merchant;

use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use think\db\exception\DbException;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use app\common\model\system\merchant\MerchantAd;

class MerchantAdDao extends BaseDao
{

    /**
     * @return BaseModel
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return MerchantAd::class;
    }

    /**
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws DataNotFoundException
     */
    public function getInfo($id)
    {
        $result = ($this->getModel())::getDB()->with(['couponIds'])->find($id);
        return method_exists($result, 'toArray') ? $result->toArray() : [];
    }
}