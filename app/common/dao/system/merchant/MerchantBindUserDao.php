<?php

namespace app\common\dao\system\merchant;

use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\system\merchant\MerchantBindUser;

class MerchantBindUserDao extends BaseDao
{

    /**
     * @return BaseModel
     * @author xaboy
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return MerchantBindUser::class;
    }

    /**
     * 设置过期的绑定关系为无效
     *
     * @return void
     * @throws \think\db\exception\DbException
     */
    public function setBindingRelationInvalid()
    {
        $query = $this->getModel()::getDB()
            ->whereTime('expire_time', '<', time())
            ->where('status', MerchantBindUser::STATUS_VALID);
        $ids = $query->column($this->getPk());
        if (!$ids) {
            return;
        }
        $updateRes = $this->getModel()::getDB()
            ->where($this->getPk(), 'in', $ids)
            ->update(['status' => MerchantBindUser::STATUS_INVALID]);
    }
}